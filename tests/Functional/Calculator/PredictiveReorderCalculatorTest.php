<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Functional\Calculator;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\PredictiveTestTrait;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface;
use Tmonier\SyliusPredictiveAnalyticsPlugin\ValueObject\PredictionFailureReason;

/**
 * Tests fonctionnels du calculateur prédictif avec base de données.
 * Scénarios US-217 : cloisonnement, moins de 3 commandes, commandes annulées.
 */
final class PredictiveReorderCalculatorTest extends KernelTestCase
{
    use PredictiveTestTrait;

    private PredictiveReorderCalculatorInterface $calculator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->calculator = self::getContainer()->get(PredictiveReorderCalculatorInterface::class);
        $this->beginTestTransaction();
    }

    protected function tearDown(): void
    {
        $this->rollBackTestTransaction();
        parent::tearDown();
    }

    /**
     * Scénario alternatif : Référence à une seule entreprise (cloisonnement).
     * Deux entreprises, chacune a ses propres intervalles, aucun mélange.
     */
    public function testCalculateRespectsScopeByCompany(): void
    {
        // Créer deux entreprises
        $acme = $this->catalog()->company('Acme');
        $buildco = $this->catalog()->company('BuildCo');

        // Créer un produit avec une variante
        $variant = $this->catalog()->product('widget', [$this->catalog()->localeCode() => 'Test Widget']);

        // Créer un customer pour Acme
        $customerAcme = $this->catalog()->customer('buyer-acme');
        $this->catalog()->member($acme, $customerAcme);

        // Créer 3 commandes pour Acme (espacées de 20 jours)
        $baseDate = new \DateTimeImmutable('2025-01-01 10:00:00');
        for ($i = 0; $i < 3; ++$i) {
            $orderDate = $baseDate->modify('+' . ($i * 20) . ' days');
            $order = $this->catalog()->order($customerAcme, 10000, $orderDate->format('Y-m-d H:i:s'));
            $orderId = $order->getId();
            $companyId = $acme->getId();
            self::assertIsInt($orderId);
            self::assertIsInt($companyId);
            $this->linkOrderToCompany($orderId, $companyId);
        }

        // Créer un customer pour BuildCo
        $customerBuildco = $this->catalog()->customer('buyer-buildco');
        $this->catalog()->member($buildco, $customerBuildco);

        // Créer 3 commandes pour BuildCo (espacées de 30 jours)
        for ($i = 0; $i < 3; ++$i) {
            $orderDate = $baseDate->modify('+' . ($i * 30) . ' days');
            $order = $this->catalog()->order($customerBuildco, 10000, $orderDate->format('Y-m-d H:i:s'));
            $orderId = $order->getId();
            $companyId = $buildco->getId();
            self::assertIsInt($orderId);
            self::assertIsInt($companyId);
            $this->linkOrderToCompany($orderId, $companyId);
        }

        // Calculer pour Acme
        $resultAcme = $this->calculator->calculate($variant, $acme);
        $this->assertSame(20.0, $resultAcme->medianIntervalDays, 'Acme doit avoir un intervalle médian de 20 jours');
        $this->assertTrue($resultAcme->isPredicted(), 'Acme doit avoir une prédiction valide');
        $this->assertNull($resultAcme->reason, 'Acme ne doit pas avoir de raison d\'échec');

        // Calculer pour BuildCo
        $resultBuildCo = $this->calculator->calculate($variant, $buildco);
        $this->assertSame(30.0, $resultBuildCo->medianIntervalDays, 'BuildCo doit avoir un intervalle médian de 30 jours');
        $this->assertTrue($resultBuildCo->isPredicted(), 'BuildCo doit avoir une prédiction valide');
        $this->assertNull($resultBuildCo->reason, 'BuildCo ne doit pas avoir de raison d\'échec');
    }

    /**
     * Scénario erreur : Moins de 3 commandes.
     * Une référence commandée 2 fois, raison "historique insuffisant" conservée.
     */
    public function testCalculateReturnsInsufficientHistoryWhenLessThan3Orders(): void
    {
        // Créer une entreprise
        $company = $this->catalog()->company('SmallCo');

        // Créer un produit avec une variante
        $variant = $this->catalog()->product('small-widget', [$this->catalog()->localeCode() => 'Small Widget']);

        // Créer un customer
        $customer = $this->catalog()->customer('buyer-small');
        $this->catalog()->member($company, $customer);

        // Créer seulement 2 commandes
        $baseDate = new \DateTimeImmutable('2025-01-01 10:00:00');
        for ($i = 0; $i < 2; ++$i) {
            $orderDate = $baseDate->modify('+' . ($i * 15) . ' days');
            $order = $this->catalog()->order($customer, 10000, $orderDate->format('Y-m-d H:i:s'));
            $orderId = $order->getId();
            $companyId = $company->getId();
            self::assertIsInt($orderId);
            self::assertIsInt($companyId);
            $this->linkOrderToCompany($orderId, $companyId);
        }

        $result = $this->calculator->calculate($variant, $company);

        $this->assertSame(PredictionFailureReason::INSUFFICIENT_HISTORY, $result->reason, 'La raison doit être INSUFFICIENT_HISTORY');
        $this->assertFalse($result->isPredicted(), 'Il ne doit pas y avoir de prédiction');
        $this->assertNull($result->medianIntervalDays, 'L\'intervalle médian doit être null');
        $this->assertStringContainsString('commandée 2 fois', $result->explanation, 'L\'explication doit mentionner "commandée 2 fois"');
        $this->assertStringContainsString('3 commandes nécessaires', $result->explanation, 'L\'explication doit mentionner "3 commandes nécessaires"');
    }

    /**
     * Scénario erreur évitée : Commandes annulées.
     * Une commande annulée exclue du calcul, mêmes règles que company-insights.
     */
    public function testCalculateExcludesCancelledOrders(): void
    {
        // Créer une entreprise
        $company = $this->catalog()->company('CancelCo');

        // Créer un produit avec une variante
        $variant = $this->catalog()->product('cancel-widget', [$this->catalog()->localeCode() => 'Cancel Widget']);

        // Créer un customer
        $customer = $this->catalog()->customer('buyer-cancel');
        $this->catalog()->member($company, $customer);

        // Créer 3 commandes completed espacées de 25 jours
        $baseDate = new \DateTimeImmutable('2025-01-01 10:00:00');
        for ($i = 0; $i < 3; ++$i) {
            $orderDate = $baseDate->modify('+' . ($i * 25) . ' days');
            $order = $this->catalog()->order($customer, 10000, $orderDate->format('Y-m-d H:i:s'));
            $orderId = $order->getId();
            $companyId = $company->getId();
            self::assertIsInt($orderId);
            self::assertIsInt($companyId);
            $this->linkOrderToCompany($orderId, $companyId);
        }

        // 1 commande cancelled (ne doit PAS être comptée)
        $cancelledOrder = $this->catalog()->order($customer, 10000, $baseDate->modify('+40 days')->format('Y-m-d H:i:s'), state: 'cancelled');
        $cancelledOrderId = $cancelledOrder->getId();
        $companyId = $company->getId();
        self::assertIsInt($cancelledOrderId);
        self::assertIsInt($companyId);
        $this->linkOrderToCompany($cancelledOrderId, $companyId);

        $result = $this->calculator->calculate($variant, $company);

        // Si les 4 commandes étaient comptées, l'intervalle médian serait différent de 25
        // Avec seulement les 3 commandes completed, l'intervalle médian doit être 25.0
        $this->assertSame(25.0, $result->medianIntervalDays, 'L\'intervalle médian doit être 25 jours (seules les 3 commandes completed comptent)');
        $this->assertTrue($result->isPredicted(), 'Il doit y avoir une prédiction valide');
        $this->assertNull($result->reason, 'Il ne doit pas y avoir de raison d\'échec');
        $this->assertStringContainsString('commandée 3 fois', $result->explanation, 'L\'explication doit mentionner "commandée 3 fois" (pas 4)');
    }

    private function linkOrderToCompany(?int $orderId, ?int $companyId): void
    {
        assert(null !== $orderId, 'Order ID must not be null');
        assert(null !== $companyId, 'Company ID must not be null');

        $this->connection()->insert('tmonier_company_order', [
            'company_id' => $companyId,
            'order_id' => $orderId,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}

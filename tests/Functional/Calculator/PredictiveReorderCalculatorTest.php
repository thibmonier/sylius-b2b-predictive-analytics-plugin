<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Functional\Calculator;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface;
use Tmonier\SyliusPredictiveAnalyticsPlugin\ValueObject\PredictionFailureReason;

/**
 * Tests fonctionnels du calculateur prédictif avec base de données.
 * Scénarios US-217 : cloisonnement, moins de 3 commandes, commandes annulées.
 */
final class PredictiveReorderCalculatorTest extends KernelTestCase
{
    private PredictiveReorderCalculatorInterface $calculator;

    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->calculator = self::getContainer()->get(PredictiveReorderCalculatorInterface::class);
        $this->connection = self::getContainer()->get('doctrine.dbal.default_connection');
    }

    /**
     * Scénario alternatif : Référence à une seule entreprise (cloisonnement).
     * Deux entreprises, chacune a ses propres intervalles, aucun mélange.
     */
    public function testCalculateRespectsScopeByCompany(): void
    {
        // Créer deux entreprises
        $this->connection->insert('tmonier_company', ['id' => 1, 'name' => 'Acme Corp']);
        $this->connection->insert('tmonier_company', ['id' => 2, 'name' => 'BuildCo Ltd']);

        // Créer une variante de produit
        $this->connection->insert('sylius_product_variant', [
            'id' => 1,
            'code' => 'TEST_VARIANT_001',
        ]);

        // Créer 3 commandes pour Acme (espacées de 20 jours)
        $baseDate = new \DateTimeImmutable('2025-01-01 10:00:00');
        for ($i = 0; $i < 3; ++$i) {
            $orderId = 100 + $i;
            $orderDate = $baseDate->modify('+' . ($i * 20) . ' days');

            $this->connection->insert('sylius_order', [
                'id' => $orderId,
                'number' => 'ACME-' . str_pad((string) $i, 6, '0', \STR_PAD_LEFT),
                'checkout_state' => 'completed',
                'state' => 'new',
                'checkout_completed_at' => $orderDate->format('Y-m-d H:i:s'),
                'currency_code' => 'EUR',
                'locale_code' => 'fr_FR',
                'total' => 10000,
            ]);

            $this->connection->insert('tmonier_company_order', [
                'company_id' => 1,
                'order_id' => $orderId,
            ]);

            $this->connection->insert('sylius_order_item', [
                'order_id' => $orderId,
                'variant_id' => 1,
                'quantity' => 1,
                'unit_price' => 10000,
                'total' => 10000,
            ]);
        }

        // Créer 3 commandes pour BuildCo (espacées de 30 jours)
        for ($i = 0; $i < 3; ++$i) {
            $orderId = 200 + $i;
            $orderDate = $baseDate->modify('+' . ($i * 30) . ' days');

            $this->connection->insert('sylius_order', [
                'id' => $orderId,
                'number' => 'BUILD-' . str_pad((string) $i, 6, '0', \STR_PAD_LEFT),
                'checkout_state' => 'completed',
                'state' => 'new',
                'checkout_completed_at' => $orderDate->format('Y-m-d H:i:s'),
                'currency_code' => 'EUR',
                'locale_code' => 'fr_FR',
                'total' => 10000,
            ]);

            $this->connection->insert('tmonier_company_order', [
                'company_id' => 2,
                'order_id' => $orderId,
            ]);

            $this->connection->insert('sylius_order_item', [
                'order_id' => $orderId,
                'variant_id' => 1,
                'quantity' => 1,
                'unit_price' => 10000,
                'total' => 10000,
            ]);
        }

        // Créer des mocks d'entreprise et de variante
        $acme = $this->createCompanyMock(1);
        $buildco = $this->createCompanyMock(2);
        $variant = $this->createVariantMock(1);

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
        $this->connection->insert('tmonier_company', ['id' => 10, 'name' => 'SmallCo']);

        // Créer une variante
        $this->connection->insert('sylius_product_variant', [
            'id' => 10,
            'code' => 'TEST_VARIANT_010',
        ]);

        // Créer seulement 2 commandes
        $baseDate = new \DateTimeImmutable('2025-01-01 10:00:00');
        for ($i = 0; $i < 2; ++$i) {
            $orderId = 1000 + $i;
            $orderDate = $baseDate->modify('+' . ($i * 15) . ' days');

            $this->connection->insert('sylius_order', [
                'id' => $orderId,
                'number' => 'SMALL-' . str_pad((string) $i, 6, '0', \STR_PAD_LEFT),
                'checkout_state' => 'completed',
                'state' => 'new',
                'checkout_completed_at' => $orderDate->format('Y-m-d H:i:s'),
                'currency_code' => 'EUR',
                'locale_code' => 'fr_FR',
                'total' => 10000,
            ]);

            $this->connection->insert('tmonier_company_order', [
                'company_id' => 10,
                'order_id' => $orderId,
            ]);

            $this->connection->insert('sylius_order_item', [
                'order_id' => $orderId,
                'variant_id' => 10,
                'quantity' => 1,
                'unit_price' => 10000,
                'total' => 10000,
            ]);
        }

        $company = $this->createCompanyMock(10);
        $variant = $this->createVariantMock(10);

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
        $this->connection->insert('tmonier_company', ['id' => 20, 'name' => 'CancelCo']);

        // Créer une variante
        $this->connection->insert('sylius_product_variant', [
            'id' => 20,
            'code' => 'TEST_VARIANT_020',
        ]);

        // Créer 3 commandes completed + 1 cancelled
        $baseDate = new \DateTimeImmutable('2025-01-01 10:00:00');

        // 3 commandes completed espacées de 25 jours
        for ($i = 0; $i < 3; ++$i) {
            $orderId = 2000 + $i;
            $orderDate = $baseDate->modify('+' . ($i * 25) . ' days');

            $this->connection->insert('sylius_order', [
                'id' => $orderId,
                'number' => 'CANCEL-' . str_pad((string) $i, 6, '0', \STR_PAD_LEFT),
                'checkout_state' => 'completed',
                'state' => 'new',
                'checkout_completed_at' => $orderDate->format('Y-m-d H:i:s'),
                'currency_code' => 'EUR',
                'locale_code' => 'fr_FR',
                'total' => 10000,
            ]);

            $this->connection->insert('tmonier_company_order', [
                'company_id' => 20,
                'order_id' => $orderId,
            ]);

            $this->connection->insert('sylius_order_item', [
                'order_id' => $orderId,
                'variant_id' => 20,
                'quantity' => 1,
                'unit_price' => 10000,
                'total' => 10000,
            ]);
        }

        // 1 commande cancelled (ne doit PAS être comptée)
        $this->connection->insert('sylius_order', [
            'id' => 2010,
            'number' => 'CANCEL-' . str_pad('10', 6, '0', \STR_PAD_LEFT),
            'checkout_state' => 'completed',
            'state' => 'cancelled',
            'checkout_completed_at' => $baseDate->modify('+40 days')->format('Y-m-d H:i:s'),
            'currency_code' => 'EUR',
            'locale_code' => 'fr_FR',
            'total' => 10000,
        ]);

        $this->connection->insert('tmonier_company_order', [
            'company_id' => 20,
            'order_id' => 2010,
        ]);

        $this->connection->insert('sylius_order_item', [
            'order_id' => 2010,
            'variant_id' => 20,
            'quantity' => 1,
            'unit_price' => 10000,
            'total' => 10000,
        ]);

        $company = $this->createCompanyMock(20);
        $variant = $this->createVariantMock(20);

        $result = $this->calculator->calculate($variant, $company);

        // Si les 4 commandes étaient comptées, l'intervalle médian serait différent de 25
        // Avec seulement les 3 commandes completed, l'intervalle médian doit être 25.0
        $this->assertSame(25.0, $result->medianIntervalDays, 'L\'intervalle médian doit être 25 jours (seules les 3 commandes completed comptent)');
        $this->assertTrue($result->isPredicted(), 'Il doit y avoir une prédiction valide');
        $this->assertNull($result->reason, 'Il ne doit pas y avoir de raison d\'échec');
        $this->assertStringContainsString('commandée 3 fois', $result->explanation, 'L\'explication doit mentionner "commandée 3 fois" (pas 4)');
    }

    /**
     * @return \Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface
     */
    private function createCompanyMock(int $id): object
    {
        $company = $this->createMock(\Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface::class);
        $company->method('getId')->willReturn($id);

        return $company;
    }

    /**
     * @return \Sylius\Component\Core\Model\ProductVariantInterface
     */
    private function createVariantMock(int $id): object
    {
        $variant = $this->createMock(\Sylius\Component\Core\Model\ProductVariantInterface::class);
        $variant->method('getId')->willReturn($id);

        return $variant;
    }
}

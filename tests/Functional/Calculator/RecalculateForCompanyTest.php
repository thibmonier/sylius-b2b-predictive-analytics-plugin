<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Functional\Calculator;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\PredictiveTestTrait;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface;

/**
 * Tests fonctionnels du recalcul pour toutes les références d'une entreprise.
 */
final class RecalculateForCompanyTest extends KernelTestCase
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
     * Test du recalcul : prédictions persistées pour toutes les références commandées.
     */
    public function testRecalculateForCompanyStoresPredictionsForAllOrderedVariants(): void
    {
        // Créer une entreprise
        $company = $this->catalog()->company('TestCo');

        // Créer 2 variantes
        $variant1 = $this->catalog()->product('widget-a', [$this->catalog()->localeCode() => 'Widget A']);
        $variant2 = $this->catalog()->product('widget-b', [$this->catalog()->localeCode() => 'Widget B']);

        // Créer un customer membre de l'entreprise
        $customer = $this->catalog()->customer('buyer-test');
        $this->catalog()->member($company, $customer);

        // Créer 3 commandes pour variant1 (espacées de 20 jours) -> prédiction valide
        $baseDate = new \DateTimeImmutable('2025-01-01 10:00:00');
        for ($i = 0; $i < 3; ++$i) {
            $orderDate = $baseDate->modify('+' . ($i * 20) . ' days');
            $this->catalog()->orderOfLines($customer, [[$variant1, 1, 10000]], $orderDate->format('Y-m-d H:i:s'));
        }

        // Créer 2 commandes pour variant2 (< 3) -> historique insuffisant
        for ($i = 0; $i < 2; ++$i) {
            $orderDate = $baseDate->modify('+' . ($i * 15) . ' days');
            $this->catalog()->orderOfLines($customer, [[$variant2, 1, 10000]], $orderDate->format('Y-m-d H:i:s'));
        }

        // Recalculer pour l'entreprise
        $count = $this->calculator->recalculateForCompany($company);

        // 2 variantes commandées
        $this->assertSame(2, $count, 'Le recalcul doit traiter 2 variantes');

        // Vérifier la persistance en base
        $predictions = $this->connection()->fetchAllAssociative(
            'SELECT variant_id, median_interval_days, order_count, reason FROM tmonier_predictive_stored_prediction WHERE company_id = ? ORDER BY variant_id',
            [$company->getId()],
        );

        $this->assertCount(2, $predictions, 'Doit avoir 2 prédictions persistées');

        // Variant1: 3 commandes, intervalle 20 jours
        $pred1 = $predictions[0];
        \assert(is_numeric($pred1['variant_id']));
        \assert(is_numeric($pred1['median_interval_days']));
        \assert(is_numeric($pred1['order_count']));
        $variant1Id = $variant1->getId();
        \assert(is_int($variant1Id));
        $this->assertSame($variant1Id, (int) $pred1['variant_id']);
        $this->assertSame(20.0, (float) $pred1['median_interval_days']);
        $this->assertSame(3, (int) $pred1['order_count']);
        $this->assertNull($pred1['reason']);

        // Variant2: 2 commandes, historique insuffisant
        $pred2 = $predictions[1];
        \assert(is_numeric($pred2['variant_id']));
        \assert(is_numeric($pred2['order_count']));
        $variant2Id = $variant2->getId();
        \assert(is_int($variant2Id));
        $this->assertSame($variant2Id, (int) $pred2['variant_id']);
        $this->assertNull($pred2['median_interval_days']);
        $this->assertSame(2, (int) $pred2['order_count']);
        $this->assertSame('INSUFFICIENT_HISTORY', $pred2['reason']);
    }

    /**
     * Test de l'idempotence : recalculer plusieurs fois ne doit pas dupliquer.
     */
    public function testRecalculateForCompanyIsIdempotent(): void
    {
        $company = $this->catalog()->company('IdempotentCo');
        $variant = $this->catalog()->product('widget-c', [$this->catalog()->localeCode() => 'Widget C']);
        $customer = $this->catalog()->customer('buyer-idempotent');
        $this->catalog()->member($company, $customer);

        // Créer 3 commandes
        $baseDate = new \DateTimeImmutable('2025-01-01 10:00:00');
        for ($i = 0; $i < 3; ++$i) {
            $orderDate = $baseDate->modify('+' . ($i * 25) . ' days');
            $this->catalog()->orderOfLines($customer, [[$variant, 1, 10000]], $orderDate->format('Y-m-d H:i:s'));
        }

        // Premier recalcul
        $count1 = $this->calculator->recalculateForCompany($company);
        $this->assertSame(1, $count1);

        // Deuxième recalcul (idempotent)
        $count2 = $this->calculator->recalculateForCompany($company);
        $this->assertSame(1, $count2, 'Le recalcul doit rester idempotent');

        // Vérifier qu'il n'y a qu'une seule ligne en base (pas de duplication)
        $result = $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM tmonier_predictive_stored_prediction WHERE company_id = ?',
            [$company->getId()],
        );
        \assert(is_numeric($result));
        $rowCount = (int) $result;
        $this->assertSame(1, $rowCount, 'Doit avoir exactement 1 prédiction (idempotence)');
    }
}

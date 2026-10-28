<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Unit\Calculator;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\Dbal\DbalPredictiveReorderCalculator;

/**
 * Tests unitaires du calcul de la médiane (scénarios US-217).
 * Ces tests utilisent une implémentation stub pour tester la logique sans base de données.
 */
final class MedianCalculationTest extends TestCase
{
    /**
     * Scénario nominal : Intervalle calculé.
     * 5 commandes tous les 28 jours, médiane = 28 jours.
     */
    public function testCalculateMedianWithRegularIntervals(): void
    {
        $calculator = new DbalPredictiveReorderCalculator(
            $this->createMock(\Doctrine\DBAL\Connection::class),
            $this->createCountedOrderSql(),
            $this->createMock(\Doctrine\ORM\EntityManagerInterface::class),
            $this->createMock(\Sylius\Component\Product\Repository\ProductVariantRepositoryInterface::class),
        );

        // On teste la méthode privée calculateMedian via réflexion
        $method = new \ReflectionMethod($calculator, 'calculateMedian');

        $median = $method->invoke($calculator, [28, 28, 28, 28]);

        $this->assertSame(28.0, $median);
    }

    /**
     * Scénario alternatif : Intervalles irréguliers.
     * Intervalles de 20, 21, 60 et 22 jours, médiane = 21,5 jours (pas 22).
     */
    public function testCalculateMedianWithIrregularIntervals(): void
    {
        $calculator = new DbalPredictiveReorderCalculator(
            $this->createMock(\Doctrine\DBAL\Connection::class),
            $this->createCountedOrderSql(),
            $this->createMock(\Doctrine\ORM\EntityManagerInterface::class),
            $this->createMock(\Sylius\Component\Product\Repository\ProductVariantRepositoryInterface::class),
        );

        $method = new \ReflectionMethod($calculator, 'calculateMedian');

        $median = $method->invoke($calculator, [20, 21, 60, 22]);

        $this->assertSame(21.5, $median, 'La médiane doit être 21,5 (pas arrondie à 22), non faussée par la valeur extrême 60');
    }

    /**
     * Test avec nombre impair d'intervalles.
     */
    public function testCalculateMedianWithOddNumberOfIntervals(): void
    {
        $calculator = new DbalPredictiveReorderCalculator(
            $this->createMock(\Doctrine\DBAL\Connection::class),
            $this->createCountedOrderSql(),
            $this->createMock(\Doctrine\ORM\EntityManagerInterface::class),
            $this->createMock(\Sylius\Component\Product\Repository\ProductVariantRepositoryInterface::class),
        );

        $method = new \ReflectionMethod($calculator, 'calculateMedian');

        $median = $method->invoke($calculator, [10, 20, 30]);

        $this->assertSame(20.0, $median);
    }

    private function createCountedOrderSql(): \Tmonier\SyliusCompanyInsightsPlugin\Statistics\CountedOrderSql
    {
        $pendingCondition = new class() implements \Tmonier\SyliusCompanyInsightsPlugin\Approval\PendingOrderConditionInterface {
            public function sql(string $alias): ?string
            {
                return null;
            }
        };

        return new \Tmonier\SyliusCompanyInsightsPlugin\Statistics\CountedOrderSql($pendingCondition);
    }
}

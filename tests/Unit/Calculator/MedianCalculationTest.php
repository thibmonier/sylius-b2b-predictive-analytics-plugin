<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Unit\Calculator;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\IntervalCalculator;

/**
 * Tests unitaires du calcul de la médiane (scénarios US-217).
 * US-240: Tests pointent sur IntervalCalculator::calculateMedian() (fonctions pures extraites).
 */
final class MedianCalculationTest extends TestCase
{
    /**
     * Scénario nominal : Intervalle calculé.
     * 5 commandes tous les 28 jours, médiane = 28 jours.
     */
    public function testCalculateMedianWithRegularIntervals(): void
    {
        $median = IntervalCalculator::calculateMedian([28, 28, 28, 28]);

        $this->assertSame(28.0, $median);
    }

    /**
     * Scénario alternatif : Intervalles irréguliers.
     * Intervalles de 20, 21, 60 et 22 jours, médiane = 21,5 jours (pas 22).
     */
    public function testCalculateMedianWithIrregularIntervals(): void
    {
        $median = IntervalCalculator::calculateMedian([20, 21, 60, 22]);

        $this->assertSame(21.5, $median, 'La médiane doit être 21,5 (pas arrondie à 22), non faussée par la valeur extrême 60');
    }

    /**
     * Test avec nombre impair d'intervalles.
     */
    public function testCalculateMedianWithOddNumberOfIntervals(): void
    {
        $median = IntervalCalculator::calculateMedian([10, 20, 30]);

        $this->assertSame(20.0, $median);
    }
}

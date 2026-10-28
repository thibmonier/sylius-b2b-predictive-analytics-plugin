<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Unit\Calculator;

use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du calcul de la médiane.
 * Scénarios US-217 : intervalle calculé, intervalles irréguliers.
 */
final class MedianCalculationTest extends TestCase
{
    /**
     * Scénario nominal : Intervalle calculé.
     * 5 commandes tous les 28 jours, médiane = 28 jours.
     */
    public function testCalculateMedianWithRegularIntervals(): void
    {
        // Arrange: 5 commandes espacées de 28 jours
        $intervals = [28, 28, 28, 28];

        // Act
        $median = $this->calculateMedian($intervals);

        // Assert
        $this->assertSame(28, $median);
    }

    /**
     * Scénario alternatif : Intervalles irréguliers.
     * Intervalles de 20, 21, 60 et 22 jours, médiane (21,5 jours) non faussée par la valeur extrême.
     */
    public function testCalculateMedianWithIrregularIntervals(): void
    {
        // Arrange: intervalles irréguliers avec une valeur extrême (60)
        $intervals = [20, 21, 60, 22];

        // Act
        $median = $this->calculateMedian($intervals);

        // Assert: médiane = (21 + 22) / 2 = 21,5 arrondi à 22
        $this->assertSame(22, $median, 'La médiane ne doit pas être faussée par la valeur extrême 60');
    }

    /**
     * Test avec nombre impair d'intervalles.
     */
    public function testCalculateMedianWithOddNumberOfIntervals(): void
    {
        // Arrange: 3 intervalles
        $intervals = [10, 20, 30];

        // Act
        $median = $this->calculateMedian($intervals);

        // Assert: médiane = 20 (valeur centrale)
        $this->assertSame(20, $median);
    }

    /**
     * Test avec 2 intervalles (minimum pour calculer une médiane).
     */
    public function testCalculateMedianWithTwoIntervals(): void
    {
        // Arrange: 2 intervalles
        $intervals = [15, 25];

        // Act
        $median = $this->calculateMedian($intervals);

        // Assert: médiane = (15 + 25) / 2 = 20
        $this->assertSame(20, $median);
    }

    /**
     * Calcule la médiane d'un tableau d'entiers (copie de la méthode privée de DbalPredictiveReorderCalculator).
     *
     * @param list<int> $values
     * @return int Médiane arrondie à l'entier le plus proche
     */
    private function calculateMedian(array $values): int
    {
        sort($values);
        $count = count($values);

        if (0 === $count % 2) {
            // Nombre pair : moyenne des deux valeurs centrales
            $mid1 = $values[$count / 2 - 1];
            $mid2 = $values[$count / 2];

            return (int) round(($mid1 + $mid2) / 2);
        }

        // Nombre impair : valeur centrale
        return $values[(int) floor($count / 2)];
    }
}

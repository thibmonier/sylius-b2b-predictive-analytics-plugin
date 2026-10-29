<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator;

/**
 * Fonctions pures de calcul d'intervalles et de médiane, extraites pour faciliter les tests.
 */
final class IntervalCalculator
{
    /**
     * Calcule les intervalles en jours entre commandes successives.
     *
     * @param list<\DateTimeImmutable> $dates Dates triées par ordre chronologique
     *
     * @return list<int> Intervalles en jours
     */
    public static function calculateIntervals(array $dates): array
    {
        $intervals = [];

        for ($i = 1, $count = count($dates); $i < $count; ++$i) {
            $interval = $dates[$i]->diff($dates[$i - 1])->days;
            $intervals[] = $interval;
        }

        return $intervals;
    }

    /**
     * Calcule la médiane d'un tableau d'entiers.
     *
     * @param list<int> $values
     *
     * @return float Médiane (peut être décimale : 21.5)
     */
    public static function calculateMedian(array $values): float
    {
        if ([] === $values) {
            throw new \InvalidArgumentException('Cannot calculate median of empty array');
        }

        $sorted = $values;
        sort($sorted);
        $count = count($sorted);

        if (0 === $count % 2) {
            // Nombre pair : moyenne des deux valeurs centrales (pas arrondie)
            $mid1 = $sorted[$count / 2 - 1];
            $mid2 = $sorted[$count / 2];

            return ($mid1 + $mid2) / 2;
        }

        // Nombre impair : valeur centrale
        return (float) $sorted[(int) floor($count / 2)];
    }
}

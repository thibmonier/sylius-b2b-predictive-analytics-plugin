<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Attrition;

use Psr\Clock\ClockInterface;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\IntervalCalculator;

/**
 * Règle explicable du score d'attrition d'une entreprise.
 *
 * - Le rythme habituel est l'intervalle médian entre les commandes des 12 derniers mois.
 * - Il faut au moins 4 commandes sur ces 12 mois, sinon : historique insuffisant, sans score.
 * - Si la première commande de l'entreprise a moins de 6 mois, aucun statut d'alerte (entreprise
 *   nouvelle ou saisonnière).
 * - Délai depuis la dernière commande > 2x le rythme : à risque ; > 3x : critique ; sinon actif.
 */
final readonly class AttritionScoreCalculator
{
    private const WINDOW = '-12 months';

    private const MIN_HISTORY = '-6 months';

    private const MIN_ORDERS = 4;

    private const AT_RISK_FACTOR = 2;

    private const CRITICAL_FACTOR = 3;

    /** Plancher du rythme, pour des commandes passées le même jour (médiane de 0 jour). */
    private const MIN_MEDIAN_DAYS = 1.0;

    public function __construct(private ClockInterface $clock)
    {
    }

    /**
     * @param list<\DateTimeImmutable> $orderDates Dates des commandes comptées de l'entreprise, triées par ordre chronologique
     */
    public function calculate(array $orderDates): AttritionScore
    {
        $now = $this->clock->now();
        $windowStart = $now->modify(self::WINDOW);
        $recent = array_values(array_filter($orderDates, static fn (\DateTimeImmutable $date): bool => $date >= $windowStart));

        if (count($recent) < self::MIN_ORDERS) {
            return AttritionScore::unscored(AttritionStatus::InsufficientHistory, count($recent));
        }

        if ($orderDates[0] > $now->modify(self::MIN_HISTORY)) {
            return AttritionScore::unscored(AttritionStatus::TooRecent, count($recent));
        }

        $median = IntervalCalculator::calculateMedian(IntervalCalculator::calculateIntervals($recent));
        $daysSinceLast = $this->daysSince(end($recent), $now);

        return new AttritionScore($this->statusFor($median, $daysSinceLast), $median, $daysSinceLast, count($recent));
    }

    private function statusFor(float $median, int $daysSinceLast): AttritionStatus
    {
        $rhythm = max($median, self::MIN_MEDIAN_DAYS);

        return match (true) {
            $daysSinceLast > self::CRITICAL_FACTOR * $rhythm => AttritionStatus::Critical,
            $daysSinceLast > self::AT_RISK_FACTOR * $rhythm => AttritionStatus::AtRisk,
            default => AttritionStatus::Active,
        };
    }

    private function daysSince(\DateTimeImmutable $date, \DateTimeImmutable $now): int
    {
        if ($date > $now) {
            return 0;
        }

        return (int) $date->diff($now)->days;
    }
}

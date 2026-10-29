<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Unit\Calculator;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\AlertCalculator;

/**
 * Tests unitaires pour AlertCalculator.
 *
 * US-240: vérifie la règle de Thibaut : un client est signalé « en retard » dès qu'il dépasse
 * sa date de recommande prévue, dans la limite d'un intervalle médian de commande.
 */
final class AlertCalculatorTest extends TestCase
{
    public function testShouldAlertWhenExpectedDateIsIn3Days(): void
    {
        $now = new \DateTimeImmutable('2026-10-29 10:00:00');
        $lastOrderDate = $now->modify('-27 days'); // Il y a 27 jours
        $medianIntervalDays = 30.0; // Intervalle médian de 30 jours

        // Date attendue = lastOrderDate + 30 jours = now + 3 jours
        // Fenêtre d'alerte = now + 7 jours
        // 3 jours ≤ 7 jours → alerte

        self::assertTrue(AlertCalculator::shouldAlert($now, $lastOrderDate, $medianIntervalDays));
    }

    public function testShouldNotAlertWhenExpectedDateIsTooFarInFuture(): void
    {
        $now = new \DateTimeImmutable('2026-10-29 10:00:00');
        $lastOrderDate = $now->modify('-2 days'); // Il y a 2 jours
        $medianIntervalDays = 30.0; // Intervalle médian de 30 jours

        // Date attendue = lastOrderDate + 30 jours = now + 28 jours
        // Fenêtre d'alerte = now + 7 jours
        // 28 jours > 7 jours → pas d'alerte

        self::assertFalse(AlertCalculator::shouldAlert($now, $lastOrderDate, $medianIntervalDays));
    }

    /**
     * Règle de Thibaut : un client est « en retard » tant que le retard ≤ 1 intervalle médian.
     *
     * Ce test vérifie la borne exacte du retard maximum accepté.
     */
    public function testShouldAlertWhenLateByExactlyOneMedianInterval(): void
    {
        $now = new \DateTimeImmutable('2026-10-29 10:00:00');
        $medianIntervalDays = 30.0; // Intervalle médian de 30 jours

        // Dernière commande il y a 60 jours (2× l'intervalle médian)
        // Date attendue = now - 60 + 30 = now - 30 jours (retard de 30 jours)
        // Retard maximum accepté = 1× intervalle médian = 30 jours
        // Retard actuel = 30 jours → à la borne exacte → alerte
        $lastOrderDate = $now->modify('-60 days');

        self::assertTrue(
            AlertCalculator::shouldAlert($now, $lastOrderDate, $medianIntervalDays),
            'A delay of exactly one median interval should still trigger an alert (Thibaut\'s rule)',
        );
    }

    /**
     * Règle de Thibaut : un client n'est plus « en retard » si le retard > 1 intervalle médian.
     *
     * Ce test vérifie que l'alerte n'est plus déclenchée au-delà de la limite.
     */
    public function testShouldNotAlertWhenLateByMoreThanOneMedianInterval(): void
    {
        $now = new \DateTimeImmutable('2026-10-29 10:00:00');
        $medianIntervalDays = 30.0; // Intervalle médian de 30 jours

        // Dernière commande il y a 61 jours (> 2× l'intervalle médian)
        // Date attendue = now - 61 + 30 = now - 31 jours (retard de 31 jours)
        // Retard maximum accepté = 1× intervalle médian = 30 jours
        // Retard actuel = 31 jours > 30 jours → pas d'alerte
        $lastOrderDate = $now->modify('-61 days');

        self::assertFalse(
            AlertCalculator::shouldAlert($now, $lastOrderDate, $medianIntervalDays),
            'A delay of more than one median interval should not trigger an alert',
        );
    }

    public function testCalculateExpectedDateWithIntegerInterval(): void
    {
        $lastOrderDate = new \DateTimeImmutable('2026-10-01 10:00:00');
        $medianIntervalDays = 30.0;

        $expectedDate = AlertCalculator::calculateExpectedDate($lastOrderDate, $medianIntervalDays);

        self::assertSame('2026-10-31 10:00:00', $expectedDate->format('Y-m-d H:i:s'));
    }

    public function testCalculateExpectedDateWithDecimalInterval(): void
    {
        $lastOrderDate = new \DateTimeImmutable('2026-10-01 10:00:00');
        $medianIntervalDays = 21.5; // 21 jours et 12 heures

        $expectedDate = AlertCalculator::calculateExpectedDate($lastOrderDate, $medianIntervalDays);

        self::assertSame('2026-10-22 22:00:00', $expectedDate->format('Y-m-d H:i:s'));
    }
}

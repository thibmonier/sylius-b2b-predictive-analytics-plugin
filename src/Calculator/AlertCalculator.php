<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator;

/**
 * Calcule si une prédiction déclenche une alerte de réassort.
 *
 * Règle métier (confirmée par Thibaut, 2026-10-29, registre sprint 36) :
 * Un client est signalé « en retard » dès qu'il dépasse sa date de recommande prévue,
 * dans la limite d'un intervalle médian de commande.
 */
final class AlertCalculator
{
    /** Fenêtre d'alerte : échéance dans les 7 jours. */
    private const ALERT_WINDOW_DAYS = 7;

    /**
     * Calcule si une prédiction déclenche une alerte.
     *
     * Critères :
     * - Date attendue ≤ now + 7 jours (fenêtre d'alerte)
     * - Date attendue ≥ now - medianIntervalDays (retard maximum toléré = 1 intervalle médian)
     *
     * @param \DateTimeImmutable $now              Date actuelle
     * @param \DateTimeImmutable $lastOrderDate    Date de la dernière commande
     * @param float              $medianIntervalDays Intervalle médian en jours
     *
     * @return bool True si une alerte doit être déclenchée
     */
    public static function shouldAlert(
        \DateTimeImmutable $now,
        \DateTimeImmutable $lastOrderDate,
        float $medianIntervalDays,
    ): bool {
        $expectedDate = self::calculateExpectedDate($lastOrderDate, $medianIntervalDays);
        $sevenDaysFromNow = $now->modify(sprintf('+%d days', self::ALERT_WINDOW_DAYS));

        // Critère 1 : date attendue dans la fenêtre de 7 jours
        if ($expectedDate > $sevenDaysFromNow) {
            return false; // Trop loin dans le futur
        }

        // Critère 2 : retard maximum accepté = un intervalle médian
        $medianDays = (int) $medianIntervalDays;
        $earliestDate = $now->modify(sprintf('-%d days', $medianDays));

        return $expectedDate >= $earliestDate;
    }

    /**
     * Calcule la date attendue de la prochaine commande.
     *
     * @param \DateTimeImmutable $lastOrderDate    Date de la dernière commande
     * @param float              $medianIntervalDays Intervalle médian en jours (peut être décimal : 21.5)
     *
     * @return \DateTimeImmutable Date attendue de la prochaine commande
     */
    public static function calculateExpectedDate(
        \DateTimeImmutable $lastOrderDate,
        float $medianIntervalDays,
    ): \DateTimeImmutable {
        $intervalDays = (int) $medianIntervalDays;
        $intervalHours = (int) (($medianIntervalDays - $intervalDays) * 24);
        $intervalSpec = sprintf('P%dDT%dH', $intervalDays, $intervalHours);

        return $lastOrderDate->add(new \DateInterval($intervalSpec));
    }
}

<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\ValueObject;

/**
 * Prédiction de réassort pour une référence et une entreprise.
 * Si aucune prédiction possible, le champ reason explique pourquoi.
 */
final readonly class ReorderPrediction
{
    private function __construct(
        /** Intervalle médian en jours (peut être décimal : 21.5 jours). Null si aucune prédiction. */
        public ?float $medianIntervalDays,
        /** Date de la dernière commande (ou null si aucune commande) */
        public ?\DateTimeImmutable $lastOrderDate,
        /** Date attendue du prochain réassort. Null si aucune prédiction. */
        public ?\DateTimeImmutable $expectedReorderDate,
        /** Explication lisible (ex: "commandée 5 fois, tous les 21.5 jours en médiane") */
        public string $explanation,
        /** Raison si aucune prédiction possible. Null si prédiction valide. */
        public ?PredictionFailureReason $reason,
    ) {
    }

    public static function predicted(
        float $medianIntervalDays,
        \DateTimeImmutable $lastOrderDate,
        int $orderCount,
    ): self {
        // Si la médiane est un nombre entier, on l'affiche sans décimale
        $days = floor($medianIntervalDays) === $medianIntervalDays ? (int) $medianIntervalDays : $medianIntervalDays;
        $explanation = sprintf(
            'commandée %d fois, tous les %s jours en médiane',
            $orderCount,
            $days,
        );

        $intervalDays = (int) $medianIntervalDays;
        $intervalHours = (int) (($medianIntervalDays - $intervalDays) * 24);
        $intervalSpec = sprintf('P%dDT%dH', $intervalDays, $intervalHours);
        $expectedReorderDate = $lastOrderDate->add(new \DateInterval($intervalSpec));

        return new self(
            medianIntervalDays: $medianIntervalDays,
            lastOrderDate: $lastOrderDate,
            expectedReorderDate: $expectedReorderDate,
            explanation: $explanation,
            reason: null,
        );
    }

    public static function insufficientHistory(int $orderCount): self
    {
        $explanation = sprintf(
            'historique insuffisant : commandée %d fois, 3 commandes nécessaires',
            $orderCount,
        );

        return new self(
            medianIntervalDays: null,
            lastOrderDate: null,
            expectedReorderDate: null,
            explanation: $explanation,
            reason: PredictionFailureReason::INSUFFICIENT_HISTORY,
        );
    }

    public function isPredicted(): bool
    {
        return !$this->reason instanceof \Tmonier\SyliusPredictiveAnalyticsPlugin\ValueObject\PredictionFailureReason;
    }
}

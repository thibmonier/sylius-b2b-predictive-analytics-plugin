<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\ValueObject;

/**
 * Prédiction de réassort pour une référence et une entreprise.
 */
final readonly class ReorderPrediction
{
    public function __construct(
        /** Intervalle médian en jours entre les commandes successives */
        public int $medianIntervalDays,
        /** Date de la dernière commande */
        public \DateTimeImmutable $lastOrderDate,
        /** Date attendue du prochain réassort (lastOrderDate + medianIntervalDays) */
        public \DateTimeImmutable $expectedReorderDate,
        /** Explication lisible (ex: "commandée 5 fois, tous les 28 jours en médiane") */
        public string $explanation,
    ) {
    }
}

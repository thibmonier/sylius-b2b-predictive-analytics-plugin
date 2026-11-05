<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Attrition;

/**
 * Résultat explicable du score d'attrition d'une entreprise.
 *
 * L'intervalle médian et le délai depuis la dernière commande ne sont renseignés que lorsqu'un
 * score existe (statut actif, à risque ou critique).
 */
final readonly class AttritionScore
{
    public function __construct(
        public AttritionStatus $status,
        public ?float $medianIntervalDays,
        public ?int $daysSinceLastOrder,
        public int $orderCount,
    ) {
    }

    public static function unscored(AttritionStatus $status, int $orderCount): self
    {
        return new self($status, null, null, $orderCount);
    }
}

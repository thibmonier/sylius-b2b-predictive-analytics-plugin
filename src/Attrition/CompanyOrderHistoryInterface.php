<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Attrition;

interface CompanyOrderHistoryInterface
{
    /**
     * Dates des commandes comptées d'une entreprise, triées par ordre chronologique.
     *
     * @return list<\DateTimeImmutable>
     */
    public function orderDatesOf(int $companyId): array;

    /**
     * @return list<int> Identifiants de toutes les entreprises
     */
    public function companyIds(): array;
}

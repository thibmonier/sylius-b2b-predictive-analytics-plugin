<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Attrition;

final readonly class CompanyAttritionScorer
{
    public function __construct(
        private CompanyOrderHistoryInterface $history,
        private AttritionScoreCalculator $calculator,
    ) {
    }

    public function scoreOf(int $companyId): AttritionScore
    {
        return $this->calculator->calculate($this->history->orderDatesOf($companyId));
    }

    /**
     * @return list<int> Identifiants des entreprises dont le statut est celui demandé
     */
    public function companyIdsWithStatus(AttritionStatus $status): array
    {
        return array_values(array_filter(
            $this->history->companyIds(),
            fn (int $companyId): bool => $this->scoreOf($companyId)->status === $status,
        ));
    }
}

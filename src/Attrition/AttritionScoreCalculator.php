<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Attrition;

use Psr\Clock\ClockInterface;

final readonly class AttritionScoreCalculator
{
    public function __construct(private ClockInterface $clock)
    {
    }

    /**
     * @param list<\DateTimeImmutable> $orderDates Dates des commandes comptées de l'entreprise, triées par ordre chronologique
     */
    public function calculate(array $orderDates): AttritionScore
    {
        return AttritionScore::unscored(AttritionStatus::Active, 0);
    }
}

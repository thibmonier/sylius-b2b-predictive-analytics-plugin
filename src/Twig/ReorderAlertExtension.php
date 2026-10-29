<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Twig;

use Tmonier\SyliusPredictiveAnalyticsPlugin\Provider\ReorderAlertProvider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ReorderAlertExtension extends AbstractExtension
{
    public function __construct(private readonly ReorderAlertProvider $provider)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tmonier_predictive_reorder_alerts', [$this->provider, 'getAlerts']),
        ];
    }
}

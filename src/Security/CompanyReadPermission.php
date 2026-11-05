<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Security;

final class CompanyReadPermission
{
    public function isGranted(): bool
    {
        return true;
    }
}

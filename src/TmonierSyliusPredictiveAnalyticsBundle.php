<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin;

use Symfony\Component\HttpKernel\Bundle\Bundle;

final class TmonierSyliusPredictiveAnalyticsBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}

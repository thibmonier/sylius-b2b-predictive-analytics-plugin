<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\DenyCompanyReadChecker;

return static function (ContainerConfigurator $container): void {
    $container->services()->set(DenyCompanyReadChecker::class);
};

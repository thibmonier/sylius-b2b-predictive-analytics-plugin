<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin;

use Sylius\Bundle\ResourceBundle\Controller\AuthorizationCheckerInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;

/**
 * Contrôle d'autorisation de l'application de test : tout est permis, sauf la lecture des
 * entreprises lorsqu'un test l'interdit. Il joue le rôle du contrôle d'autorisation qu'un
 * projet hôte branche sur `sylius_resource.authorization_checker`.
 */
final class DenyCompanyReadChecker implements AuthorizationCheckerInterface
{
    public const COMPANY_READ = 'tmonier_company_accounts.company.show';

    public static bool $denyCompanyRead = false;

    public function isGranted(RequestConfiguration $configuration, string $permission): bool
    {
        return !(self::$denyCompanyRead && self::COMPANY_READ === $permission);
    }
}

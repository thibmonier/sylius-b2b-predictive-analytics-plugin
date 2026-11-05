<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Security;

use Sylius\Bundle\ResourceBundle\Controller\AuthorizationCheckerInterface;
use Sylius\Bundle\ResourceBundle\Controller\Parameters;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Resource\Metadata\RegistryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Permission de lecture des entreprises dans l'admin.
 *
 * Sylius ne connaît pas de permission par ressource : ses routes `permission: true` interrogent le
 * contrôle d'autorisation `sylius_resource.authorization_checker`, qui laisse tout passer par défaut.
 * Le score d'attrition interroge ce même contrôle avec la permission de lecture d'une entreprise
 * (`tmonier_company_accounts.company.show`) : un projet qui branche son propre contrôle d'autorisation
 * garde ainsi la main, sans mécanisme parallèle.
 */
final readonly class CompanyReadPermission
{
    private const RESOURCE = 'tmonier_company_accounts.company';

    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private RegistryInterface $registry,
        private RequestStack $requestStack,
    ) {
    }

    public function isGranted(): bool
    {
        $configuration = new RequestConfiguration(
            $this->registry->get(self::RESOURCE),
            $this->requestStack->getCurrentRequest() ?? new Request(),
            new Parameters(['permission' => true]),
        );

        return $this->authorizationChecker->isGranted($configuration, $configuration->getPermission('show'));
    }
}

<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Twig;

use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Attrition\AttritionScore;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Attrition\CompanyAttritionScorer;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Security\CompanyReadPermission;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AttritionExtension extends AbstractExtension
{
    public function __construct(
        private readonly CompanyAttritionScorer $scorer,
        private readonly CompanyReadPermission $permission,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tmonier_predictive_company_attrition', $this->attritionOf(...)),
        ];
    }

    /** Le score de l'entreprise, ou null si l'administrateur n'a pas la permission de lire les entreprises. */
    private function attritionOf(CompanyInterface $company): ?AttritionScore
    {
        $companyId = $company->getId();
        if (null === $companyId || !$this->permission->isGranted()) {
            return null;
        }

        return $this->scorer->scoreOf($companyId);
    }
}

<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Grid;

use Sylius\Component\Grid\Data\DataSourceInterface;
use Sylius\Component\Grid\Filtering\FilterInterface;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Attrition\AttritionStatus;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Attrition\CompanyAttritionScorer;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Security\CompanyReadPermission;

/**
 * Filtre « actif / à risque / critique » de la liste des entreprises. Le statut n'est pas une colonne :
 * le filtre calcule les entreprises concernées avec la même règle que la fiche, puis restreint la liste.
 */
final readonly class AttritionFilter implements FilterInterface
{
    private const FILTERABLE = [AttritionStatus::Active, AttritionStatus::AtRisk, AttritionStatus::Critical];

    public function __construct(
        private CompanyAttritionScorer $scorer,
        private CompanyReadPermission $permission,
    ) {
    }

    public function apply(DataSourceInterface $dataSource, string $name, $data, array $options): void
    {
        $status = is_string($data) ? AttritionStatus::tryFrom($data) : null;
        if (null === $status || !in_array($status, self::FILTERABLE, true) || !$this->permission->isGranted()) {
            return;
        }

        $companyIds = $this->scorer->companyIdsWithStatus($status);

        // Une liste vide ne doit pas devenir « IN ()» : aucun identifiant ne vaut 0.
        $dataSource->restrict($dataSource->getExpressionBuilder()->in('id', [] === $companyIds ? [0] : $companyIds));
    }
}

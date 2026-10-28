<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator;

use Sylius\Component\Core\Model\ProductVariantInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusPredictiveAnalyticsPlugin\ValueObject\ReorderPrediction;

/**
 * Calcule l'intervalle médian de réassort pour une référence et une entreprise.
 */
interface PredictiveReorderCalculatorInterface
{
    /**
     * Calcule la prédiction de réassort pour une référence et une entreprise.
     *
     * @return ReorderPrediction|null Null si historique insuffisant (< 3 commandes)
     */
    public function calculate(ProductVariantInterface $variant, CompanyInterface $company): ?ReorderPrediction;

    /**
     * Recalcule toutes les prédictions pour une entreprise.
     *
     * @return int Nombre de prédictions calculées
     */
    public function recalculateForCompany(CompanyInterface $company): int;
}

<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\Dbal;

use Doctrine\DBAL\Connection;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface;
use Tmonier\SyliusPredictiveAnalyticsPlugin\ValueObject\ReorderPrediction;

/**
 * Calcule l'intervalle médian de réassort via SQL.
 *
 * Règles (comme company-insights) :
 * - Commandes complétées (checkout_completed_at IS NOT NULL)
 * - Non annulées (state <> 'cancelled')
 * - Au moins 3 commandes pour calculer une prédiction
 */
final readonly class DbalPredictiveReorderCalculator implements PredictiveReorderCalculatorInterface
{
    /** Minimum de commandes nécessaires pour calculer une prédiction */
    private const MIN_ORDERS = 3;

    public function __construct(
        private Connection $connection,
    ) {
    }

    public function calculate(ProductVariantInterface $variant, CompanyInterface $company): ReorderPrediction
    {
        $dates = $this->fetchOrderDates($variant->getId(), $company->getId());

        if (count($dates) < self::MIN_ORDERS) {
            return ReorderPrediction::insufficientHistory('historique insuffisant');
        }

        $intervals = $this->calculateIntervals($dates);
        $medianIntervalDays = $this->calculateMedian($intervals);
        $lastOrderDate = end($dates);

        return ReorderPrediction::fromCalculation(
            medianIntervalDays: $medianIntervalDays,
            lastOrderDate: $lastOrderDate,
            orderCount: count($dates),
        );
    }

    public function recalculateForCompany(CompanyInterface $company): int
    {
        // TODO: implémenter le recalcul pour toutes les références d'une entreprise
        return 0;
    }

    /**
     * Récupère les dates de commandes complétées pour une référence et une entreprise.
     *
     * @return list<\DateTimeImmutable>
     */
    private function fetchOrderDates(int $variantId, int $companyId): array
    {
        // Requête SQL : commandes complétées, non annulées, triées par date
        $sql = <<<'SQL'
            SELECT DISTINCT o.checkout_completed_at
            FROM sylius_order o
            INNER JOIN tmonier_company_order co ON co.order_id = o.id
            INNER JOIN sylius_order_item i ON i.order_id = o.id
            WHERE co.company_id = :company_id
              AND i.variant_id = :variant_id
              AND o.checkout_state = 'completed'
              AND o.state <> 'cancelled'
              AND o.checkout_completed_at IS NOT NULL
            ORDER BY o.checkout_completed_at ASC
        SQL;

        $rows = $this->connection->fetchAllAssociative($sql, [
            'company_id' => $companyId,
            'variant_id' => $variantId,
        ]);

        return array_map(
            static fn (array $row): \DateTimeImmutable => new \DateTimeImmutable($row['checkout_completed_at']),
            $rows,
        );
    }

    /**
     * Calcule les intervalles en jours entre commandes successives.
     *
     * @param list<\DateTimeImmutable> $dates Dates triées par ordre chronologique
     * @return list<int> Intervalles en jours
     */
    private function calculateIntervals(array $dates): array
    {
        $intervals = [];

        for ($i = 1, $count = count($dates); $i < $count; ++$i) {
            $interval = $dates[$i]->diff($dates[$i - 1])->days;
            $intervals[] = $interval;
        }

        return $intervals;
    }

    /**
     * Calcule la médiane d'un tableau d'entiers.
     *
     * @param list<int> $values
     * @return float Médiane (peut être décimale : 21.5)
     */
    private function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);

        if (0 === $count % 2) {
            // Nombre pair : moyenne des deux valeurs centrales (pas arrondie)
            $mid1 = $values[$count / 2 - 1];
            $mid2 = $values[$count / 2];

            return ($mid1 + $mid2) / 2;
        }

        // Nombre impair : valeur centrale
        return (float) $values[(int) floor($count / 2)];
    }
}

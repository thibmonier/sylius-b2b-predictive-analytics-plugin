<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\Dbal;

use Doctrine\DBAL\Connection;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Statistics\CountedOrderSql;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface;
use Tmonier\SyliusPredictiveAnalyticsPlugin\ValueObject\ReorderPrediction;

/**
 * Calcule l'intervalle médian de réassort via SQL.
 *
 * Règles (comme company-insights) :
 * - Commandes complétées et non annulées (via CountedOrderSql)
 * - Au moins 3 commandes pour calculer une prédiction
 */
final readonly class DbalPredictiveReorderCalculator implements PredictiveReorderCalculatorInterface
{
    /** Minimum de commandes nécessaires pour calculer une prédiction */
    private const MIN_ORDERS = 3;

    public function __construct(
        private Connection $connection,
        private CountedOrderSql $countedOrderSql,
    ) {
    }

    public function calculate(ProductVariantInterface $variant, CompanyInterface $company): ReorderPrediction
    {
        $variantId = $variant->getId();
        $companyId = $company->getId();

        if (null === $companyId) {
            throw new \InvalidArgumentException('Company must have an ID');
        }

        if (!is_int($variantId)) {
            throw new \InvalidArgumentException('ProductVariant must have a numeric ID');
        }

        $dates = $this->fetchOrderDates($variantId, $companyId);

        if (count($dates) < self::MIN_ORDERS) {
            return ReorderPrediction::insufficientHistory(count($dates));
        }

        $intervals = $this->calculateIntervals($dates);
        $medianIntervalDays = $this->calculateMedian($intervals);
        $lastOrderDate = end($dates);

        return ReorderPrediction::predicted(
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
        // PROOF COMMIT: Break filters to prove tests detect it
        $condition = str_replace(
            ['co.company_id = :company AND ', " AND o.state <> 'cancelled'"],
            '',
            $this->countedOrderSql->counted(),
        );

        $sql = <<<SQL
            SELECT DISTINCT o.checkout_completed_at
            FROM sylius_order o
            INNER JOIN tmonier_company_order co ON co.order_id = o.id
            INNER JOIN sylius_order_item i ON i.order_id = o.id
            WHERE i.variant_id = :variant_id
              AND {$condition}
              AND o.checkout_completed_at IS NOT NULL
            ORDER BY o.checkout_completed_at ASC
        SQL;

        $rows = $this->connection->fetchAllAssociative($sql, [
            'variant_id' => $variantId,
        ]);

        return array_map(
            static function (array $row): \DateTimeImmutable {
                assert(is_string($row['checkout_completed_at']));

                return new \DateTimeImmutable($row['checkout_completed_at']);
            },
            $rows,
        );
    }

    /**
     * Calcule les intervalles en jours entre commandes successives.
     *
     * @param list<\DateTimeImmutable> $dates Dates triées par ordre chronologique
     *
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
     *
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

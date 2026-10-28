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

    public function calculate(ProductVariantInterface $variant, CompanyInterface $company): ?ReorderPrediction
    {
        // Récupérer les dates de commandes complétées pour cette référence et cette entreprise
        $dates = $this->fetchOrderDates($variant->getId(), $company->getId());

        if (count($dates) < self::MIN_ORDERS) {
            return null;
        }

        // Calculer les intervalles entre commandes successives (en jours)
        $intervals = $this->calculateIntervals($dates);

        // Calculer la médiane des intervalles
        $medianIntervalDays = $this->calculateMedian($intervals);

        // Date de la dernière commande
        $lastOrderDate = end($dates);

        // Date attendue = dernière commande + intervalle médian
        $expectedReorderDate = $lastOrderDate->add(new \DateInterval(sprintf('P%dD', $medianIntervalDays)));

        // Explication
        $explanation = sprintf(
            'commandée %d fois, tous les %d jours en médiane',
            count($dates),
            $medianIntervalDays,
        );

        return new ReorderPrediction(
            medianIntervalDays: $medianIntervalDays,
            lastOrderDate: $lastOrderDate,
            expectedReorderDate: $expectedReorderDate,
            explanation: $explanation,
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
     * @return int Médiane arrondie à l'entier le plus proche
     */
    private function calculateMedian(array $values): int
    {
        sort($values);
        $count = count($values);

        if (0 === $count % 2) {
            // Nombre pair : moyenne des deux valeurs centrales
            $mid1 = $values[$count / 2 - 1];
            $mid2 = $values[$count / 2];

            return (int) round(($mid1 + $mid2) / 2);
        }

        // Nombre impair : valeur centrale
        return $values[(int) floor($count / 2)];
    }
}

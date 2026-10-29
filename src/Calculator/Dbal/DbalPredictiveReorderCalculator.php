<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Product\Repository\ProductVariantRepositoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Statistics\CountedOrderSql;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\IntervalCalculator;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator\PredictiveReorderCalculatorInterface;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Entity\StoredPrediction;
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

    /**
     * @param ProductVariantRepositoryInterface<ProductVariantInterface> $variantRepository
     */
    public function __construct(
        private Connection $connection,
        private CountedOrderSql $countedOrderSql,
        private EntityManagerInterface $entityManager,
        private ProductVariantRepositoryInterface $variantRepository,
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

        $intervals = IntervalCalculator::calculateIntervals($dates);
        $medianIntervalDays = IntervalCalculator::calculateMedian($intervals);
        $lastOrderDate = end($dates);

        return ReorderPrediction::predicted(
            medianIntervalDays: $medianIntervalDays,
            lastOrderDate: $lastOrderDate,
            orderCount: count($dates),
        );
    }

    public function recalculateForCompany(CompanyInterface $company): int
    {
        $companyId = $company->getId();
        if (null === $companyId) {
            throw new \InvalidArgumentException('Company must have an ID');
        }

        // Récupérer toutes les variantes commandées par l'entreprise
        $variantIds = $this->fetchOrderedVariantIds($companyId);

        $count = 0;
        foreach ($variantIds as $variantId) {
            /** @var ProductVariantInterface|null $variant */
            $variant = $this->variantRepository->find($variantId);
            if (null === $variant) {
                continue;
            }

            // Récupérer les dates de commandes pour calculer orderCount
            $dates = $this->fetchOrderDates($variantId, $companyId);
            $orderCount = count($dates);

            // Calculer la prédiction
            $prediction = $this->calculate($variant, $company);

            // Persister (upsert)
            $this->storePrediction($company, $variant, $prediction, $orderCount);

            ++$count;
        }

        $this->entityManager->flush();

        return $count;
    }

    /**
     * Récupère les dates de commandes complétées pour une référence et une entreprise.
     *
     * @return list<\DateTimeImmutable>
     */
    private function fetchOrderDates(int $variantId, int $companyId): array
    {
        // Réutilise la définition des commandes comptées de company-insights
        $countedCondition = $this->countedOrderSql->counted();

        $sql = <<<SQL
            SELECT DISTINCT o.checkout_completed_at
            FROM sylius_order o
            INNER JOIN tmonier_company_order co ON co.order_id = o.id
            INNER JOIN sylius_order_item i ON i.order_id = o.id
            WHERE i.variant_id = :variant_id
              AND {$countedCondition}
              AND o.checkout_completed_at IS NOT NULL
            ORDER BY o.checkout_completed_at ASC
        SQL;

        $rows = $this->connection->fetchAllAssociative($sql, [
            'company' => $companyId,
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
     * Récupère les IDs de toutes les variantes commandées par l'entreprise.
     *
     * @return list<int>
     */
    private function fetchOrderedVariantIds(int $companyId): array
    {
        $sql = <<<SQL
            SELECT DISTINCT i.variant_id
            FROM sylius_order_item i
            INNER JOIN sylius_order o ON i.order_id = o.id
            INNER JOIN tmonier_company_order co ON co.order_id = o.id
            WHERE co.company_id = :company
            ORDER BY i.variant_id ASC
        SQL;

        $rows = $this->connection->fetchAllAssociative($sql, ['company' => $companyId]);

        return array_map(static function (array $row): int {
            \assert(is_numeric($row['variant_id']));

            return (int) $row['variant_id'];
        }, $rows);
    }

    /**
     * Persiste une prédiction (upsert : trouve ou crée).
     */
    private function storePrediction(CompanyInterface $company, ProductVariantInterface $variant, ReorderPrediction $prediction, int $orderCount): void
    {
        $repository = $this->entityManager->getRepository(StoredPrediction::class);
        $stored = $repository->findOneBy(['company' => $company, 'variant' => $variant]);

        if (null === $stored) {
            $stored = new StoredPrediction(
                $company,
                $variant,
                $prediction->medianIntervalDays,
                $prediction->lastOrderDate,
                $orderCount,
                $prediction->reason?->name,
                $prediction->explanation,
            );
            $this->entityManager->persist($stored);
        } else {
            $stored->update(
                $prediction->medianIntervalDays,
                $prediction->lastOrderDate,
                $orderCount,
                $prediction->reason?->name,
                $prediction->explanation,
            );
        }
    }
}

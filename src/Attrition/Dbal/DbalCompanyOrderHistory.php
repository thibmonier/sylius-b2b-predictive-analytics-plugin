<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Attrition\Dbal;

use Doctrine\DBAL\Connection;
use Tmonier\SyliusCompanyInsightsPlugin\Statistics\CountedOrderSql;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Attrition\CompanyOrderHistoryInterface;

/**
 * Lit les commandes comptées par company-insights (terminées, non annulées, non en attente
 * d'approbation) : l'attrition repose sur les mêmes commandes que les indicateurs.
 */
final readonly class DbalCompanyOrderHistory implements CompanyOrderHistoryInterface
{
    public function __construct(
        private Connection $connection,
        private CountedOrderSql $countedOrderSql,
    ) {
    }

    public function orderDatesOf(int $companyId): array
    {
        $sql = sprintf(
            'SELECT o.checkout_completed_at FROM %s WHERE %s AND o.checkout_completed_at IS NOT NULL ORDER BY o.checkout_completed_at ASC',
            CountedOrderSql::FROM,
            $this->countedOrderSql->counted(),
        );

        $dates = $this->connection->fetchFirstColumn($sql, ['company' => $companyId]);

        return array_map(static function (mixed $date): \DateTimeImmutable {
            \assert(is_string($date));

            return new \DateTimeImmutable($date);
        }, $dates);
    }

    public function companyIds(): array
    {
        $ids = $this->connection->fetchFirstColumn('SELECT id FROM tmonier_company ORDER BY id ASC');

        return array_map(static function (mixed $id): int {
            \assert(is_numeric($id));

            return (int) $id;
        }, $ids);
    }
}

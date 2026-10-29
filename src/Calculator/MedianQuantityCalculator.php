<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Calculator;

use Doctrine\DBAL\Connection;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Statistics\CountedOrderSql;

/**
 * Calcule la quantité médiane commandée pour une référence par une entreprise.
 *
 * Utilise les mêmes règles que company-insights (CountedOrderSql::counted()) :
 * commandes complétées, non annulées, hors approbation en attente, de l'entreprise.
 */
final readonly class MedianQuantityCalculator
{
    public function __construct(
        private Connection $connection,
        private CountedOrderSql $countedOrderSql,
    ) {
    }

    public function calculate(CompanyInterface $company, ProductVariantInterface $variant): int
    {
        $companyId = $company->getId();
        $variantId = $variant->getId();

        if (null === $companyId || !is_int($variantId)) {
            return 1;
        }

        // Réutilise la définition des commandes comptées de company-insights
        $countedCondition = $this->countedOrderSql->counted();

        $sql = <<<SQL
            SELECT i.quantity
            FROM sylius_order_item i
            INNER JOIN sylius_order o ON i.order_id = o.id
            INNER JOIN tmonier_company_order co ON co.order_id = o.id
            WHERE i.variant_id = :variant_id
              AND {$countedCondition}
            ORDER BY i.quantity ASC
        SQL;

        $rows = $this->connection->fetchAllAssociative($sql, [
            'company' => $companyId,
            'variant_id' => $variantId,
        ]);

        if ([] === $rows) {
            return 1; // Valeur par défaut si aucune commande
        }

        $quantities = array_map(static function (array $row): int {
            assert(is_numeric($row['quantity']));

            return (int) $row['quantity'];
        }, $rows);

        // Calcul de la médiane
        $count = count($quantities);
        $middle = (int) floor($count / 2);

        if (0 === $count % 2) {
            // Nombre pair : moyenne des deux valeurs centrales
            return (int) round(($quantities[$middle - 1] + $quantities[$middle]) / 2);
        }

        // Nombre impair : valeur centrale
        return $quantities[$middle];
    }
}

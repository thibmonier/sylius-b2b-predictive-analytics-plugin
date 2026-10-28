<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\TestCatalog;

/** Every test runs in a transaction rolled back at the end. */
trait PredictiveTestTrait
{
    private ?TestCatalog $testCatalog = null;

    private function beginTestTransaction(): void
    {
        $this->connection()->beginTransaction();
    }

    private function rollBackTestTransaction(): void
    {
        if ($this->connection()->isTransactionActive()) {
            $this->connection()->rollBack();
        }
        $this->testCatalog = null;
    }

    private function catalog(): TestCatalog
    {
        return $this->testCatalog ??= new TestCatalog(self::getContainer());
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function connection(): Connection
    {
        return $this->entityManager()->getConnection();
    }

    /**
     * Returns diagnostic info about orders in the database for a company and variant.
     * Used in assertion messages to understand why the calculator finds no orders.
     */
    private function databaseStateForDiagnostic(int $companyId, int $variantId): string
    {
        $conn = $this->connection();

        // 1. Orders linked to the company
        $companyOrders = $conn->fetchAllAssociative(
            'SELECT order_id, company_id FROM tmonier_company_order WHERE company_id = ?',
            [$companyId],
        );

        // 2. For each order: checkout_state, state, checkout_completed_at
        $orderDetails = [];
        foreach ($companyOrders as $row) {
            $orderId = $row['order_id'];
            $order = $conn->fetchAssociative(
                'SELECT checkout_state, state, checkout_completed_at FROM sylius_order WHERE id = ?',
                [$orderId],
            );
            $orderDetails[] = sprintf(
                'order_id=%d: checkout_state=%s, state=%s, checkout_completed_at=%s',
                $orderId,
                $order['checkout_state'] ?? 'NULL',
                $order['state'] ?? 'NULL',
                $order['checkout_completed_at'] ?? 'NULL',
            );
        }

        // 3. Variant IDs in order items for these orders
        $variantIds = [];
        if (count($companyOrders) > 0) {
            $orderIds = array_column($companyOrders, 'order_id');
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $items = $conn->fetchAllAssociative(
                "SELECT DISTINCT variant_id FROM sylius_order_item WHERE order_id IN ({$placeholders})",
                $orderIds,
            );
            $variantIds = array_column($items, 'variant_id');
        }

        // 4. Pending order condition SQL
        $pendingCondition = $conn->fetchOne(
            'SELECT sql FROM tmonier_company_insights_pending_order_condition LIMIT 1',
        );

        return sprintf(
            "Company %d has %d orders: %s | Variant IDs in items: %s | Expected variant: %d | Pending condition: %s",
            $companyId,
            count($companyOrders),
            implode('; ', $orderDetails),
            implode(', ', $variantIds),
            $variantId,
            false !== $pendingCondition ? $pendingCondition : 'NULL',
        );
    }
}

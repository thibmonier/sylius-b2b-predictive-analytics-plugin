<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Integration;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\PredictiveTestTrait;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Entity\DismissedPrediction;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Entity\StoredPrediction;

/**
 * The database built by the migrations of the plugin is the one the mapping describes (US-318):
 * without the foreign keys, deleting a company, a variant or a customer left orphaned predictions.
 */
final class SchemaTest extends KernelTestCase
{
    use PredictiveTestTrait;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->beginTestTransaction();
    }

    protected function tearDown(): void
    {
        $this->rollBackTestTransaction();
        parent::tearDown();
    }

    public function testTheMigrationsBuildExactlyTheMappedSchema(): void
    {
        $entityManager = $this->entityManager();
        $metadata = array_map(
            $entityManager->getClassMetadata(...),
            [StoredPrediction::class, DismissedPrediction::class],
        );

        $statements = (new SchemaTool($entityManager))->getUpdateSchemaSql($metadata);

        // Only what the migrations fail to create or to align: the DROP statements come from the partial
        // set of classes (the tables of Sylius and of the other plugins are not part of it).
        $own = array_filter(
            $statements,
            static fn (string $sql): bool => 1 === preg_match('/\btmonier_predictive_\w+\b/', $sql) && 1 !== preg_match('/\bDROP\b/', $sql),
        );
        self::assertSame([], array_values($own));
    }

    public function testDeletingACompanyDeletesItsStoredAndDismissedPredictions(): void
    {
        $company = $this->catalog()->company();
        $variant = $this->catalog()->product('ROPE', ['en_US' => 'Rope']);
        $customer = $this->catalog()->customer();
        $this->insertPredictions($this->catalog()->id($company), $this->catalog()->id($variant), $this->catalog()->id($customer));

        $this->connection()->executeStatement('DELETE FROM tmonier_company WHERE id = ?', [$this->catalog()->id($company)]);

        self::assertSame(0, $this->rowCount('tmonier_predictive_stored_prediction', 'company_id', $this->catalog()->id($company)));
        self::assertSame(0, $this->rowCount('tmonier_predictive_dismissed_prediction', 'company_id', $this->catalog()->id($company)));
    }

    public function testDeletingACustomerDeletesTheAlertsItDismissed(): void
    {
        $company = $this->catalog()->company();
        $variant = $this->catalog()->product('HOOK', ['en_US' => 'Hook']);
        $customer = $this->catalog()->customer();
        $this->insertPredictions($this->catalog()->id($company), $this->catalog()->id($variant), $this->catalog()->id($customer));

        $this->connection()->executeStatement('DELETE FROM sylius_shop_user WHERE customer_id = ?', [$this->catalog()->id($customer)]);
        $this->connection()->executeStatement('DELETE FROM sylius_customer WHERE id = ?', [$this->catalog()->id($customer)]);

        self::assertSame(0, $this->rowCount('tmonier_predictive_dismissed_prediction', 'customer_id', $this->catalog()->id($customer)));
        self::assertSame(1, $this->rowCount('tmonier_predictive_stored_prediction', 'company_id', $this->catalog()->id($company)));
    }

    private function insertPredictions(int $companyId, int $variantId, int $customerId): void
    {
        $now = new \DateTimeImmutable();
        $this->connection()->insert('tmonier_predictive_stored_prediction', [
            'company_id' => $companyId,
            'variant_id' => $variantId,
            'order_count' => 3,
            'explanation' => 'test',
            'calculated_at' => $now,
        ], ['calculated_at' => Types::DATETIME_IMMUTABLE]);
        $this->connection()->insert('tmonier_predictive_dismissed_prediction', [
            'company_id' => $companyId,
            'variant_id' => $variantId,
            'customer_id' => $customerId,
            'type' => DismissedPrediction::TYPE_NEVER,
            'dismissed_at' => $now,
        ], ['dismissed_at' => Types::DATETIME_IMMUTABLE]);
    }

    private function rowCount(string $table, string $column, int $id): int
    {
        $count = $this->connection()->fetchOne(sprintf('SELECT COUNT(*) FROM %s WHERE %s = ?', $table, $column), [$id]);
        self::assertIsNumeric($count);

        return (int) $count;
    }
}

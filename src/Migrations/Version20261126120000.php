<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Aligns the two prediction tables on their mapping (US-318): foreign keys, their indexes and the name of
 * the unique index of the stored predictions. The rows that point to a company, a variant or a customer
 * that no longer exists are deleted first, otherwise the foreign keys could not be added.
 */
final class Version20261126120000 extends AbstractMigration
{
    /**
     * Table => column => [the table it references, the hash Doctrine names its foreign key and index with].
     *
     * @var array<string, array<string, array{string, string}>>
     */
    private const REFERENCES = [
        'tmonier_predictive_stored_prediction' => [
            'company_id' => ['tmonier_company', '88CD0F70979B1AD6'],
            'variant_id' => ['sylius_product_variant', '88CD0F703B69A9AF'],
        ],
        'tmonier_predictive_dismissed_prediction' => [
            'company_id' => ['tmonier_company', '44DB2701979B1AD6'],
            'variant_id' => ['sylius_product_variant', '44DB27013B69A9AF'],
            'customer_id' => ['sylius_customer', '44DB27019395C3F3'],
        ],
    ];

    public function getDescription(): string
    {
        return 'Ajoute les clés étrangères et les index des tables de prédictions, après suppression des lignes orphelines (tmonier/sylius-b2b-predictive-analytics-plugin, US-318).';
    }

    public function up(Schema $schema): void
    {
        foreach (self::REFERENCES as $table => $references) {
            $orphans = $this->orphanCondition($references);
            $count = $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s WHERE %s', $table, $orphans));
            $this->write(sprintf('%s : %d ligne(s) orpheline(s) supprimée(s).', $table, is_numeric($count) ? (int) $count : 0));
            $this->addSql(sprintf('DELETE FROM %s WHERE %s', $table, $orphans));

            $definition = $schema->getTable($table);
            foreach ($references as $column => [$referenced, $name]) {
                $definition->addIndex([$column], 'IDX_' . $name);
                $definition->addForeignKeyConstraint($referenced, [$column], ['id'], ['onDelete' => 'CASCADE'], 'FK_' . $name);
            }
        }

        $schema->getTable('tmonier_predictive_stored_prediction')->renameIndex('UNIQ_predictive_company_variant', 'UNIQ_company_variant');
    }

    public function down(Schema $schema): void
    {
        foreach (self::REFERENCES as $table => $references) {
            $definition = $schema->getTable($table);
            foreach ($references as [, $name]) {
                $definition->removeForeignKey('FK_' . $name);
                $definition->dropIndex('IDX_' . $name);
            }
        }

        $schema->getTable('tmonier_predictive_stored_prediction')->renameIndex('UNIQ_company_variant', 'UNIQ_predictive_company_variant');
    }

    /** @param array<string, array{string, string}> $references */
    private function orphanCondition(array $references): string
    {
        $conditions = [];
        foreach ($references as $column => [$referenced]) {
            $conditions[] = sprintf('%s NOT IN (SELECT id FROM %s)', $column, $referenced);
        }

        return implode(' OR ', $conditions);
    }
}

<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028231026 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table tmonier_predictive_stored_prediction pour le recalcul d\'entreprise (tmonier/sylius-b2b-predictive-analytics-plugin).';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('tmonier_predictive_stored_prediction');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('company_id', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('variant_id', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('median_interval_days', Types::FLOAT, ['notnull' => false, 'default' => null]);
        $table->addColumn('last_order_date', Types::DATETIME_IMMUTABLE, ['notnull' => false, 'default' => null]);
        $table->addColumn('order_count', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('reason', Types::STRING, ['length' => 50, 'notnull' => false, 'default' => null]);
        $table->addColumn('explanation', Types::TEXT, ['notnull' => true]);
        $table->addColumn('calculated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['company_id', 'variant_id'], 'UNIQ_predictive_company_variant');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('tmonier_predictive_stored_prediction');
    }
}

<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029121800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table tmonier_predictive_dismissed_prediction pour le masquage d\'alertes (tmonier/sylius-b2b-predictive-analytics-plugin).';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('tmonier_predictive_dismissed_prediction');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('company_id', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('variant_id', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('customer_id', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('type', Types::STRING, ['length' => 10, 'notnull' => true]);
        $table->addColumn('dismissed_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['company_id', 'variant_id', 'customer_id'], 'UNIQ_dismissed_company_variant_customer');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('tmonier_predictive_dismissed_prediction');
    }
}

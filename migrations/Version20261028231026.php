<?php

declare(strict_types=1);

namespace Tmonier\SyliusPredictiveAnalyticsPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028231026 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table tmonier_predictive_stored_prediction pour le recalcul d\'entreprise';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE tmonier_predictive_stored_prediction (
                id INT AUTO_INCREMENT NOT NULL,
                company_id INT NOT NULL,
                variant_id INT NOT NULL,
                median_interval_days DOUBLE PRECISION DEFAULT NULL,
                last_order_date DATETIME DEFAULT NULL,
                order_count INT NOT NULL,
                reason VARCHAR(64) DEFAULT NULL,
                explanation VARCHAR(255) DEFAULT NULL,
                calculated_at DATETIME NOT NULL,
                UNIQUE INDEX UNIQ_predictive_company_variant (company_id, variant_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tmonier_predictive_stored_prediction');
    }
}

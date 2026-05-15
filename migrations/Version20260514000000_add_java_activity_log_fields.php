<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260514000000_add_java_activity_log_fields extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align app_event_log with the Java activity/security log model used by Horizon dashboards';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('app_event_log')) {
            $this->addSql("CREATE TABLE app_event_log (
                id BIGINT AUTO_INCREMENT NOT NULL,
                user_id INT DEFAULT NULL,
                event_id VARCHAR(36) DEFAULT NULL,
                session_id VARCHAR(128) DEFAULT NULL,
                request_id VARCHAR(128) DEFAULT NULL,
                trace_id VARCHAR(64) DEFAULT NULL,
                span_id VARCHAR(32) DEFAULT NULL,
                event_type VARCHAR(50) NOT NULL,
                category VARCHAR(50) DEFAULT NULL,
                action VARCHAR(100) DEFAULT NULL,
                outcome VARCHAR(30) DEFAULT NULL,
                message LONGTEXT DEFAULT NULL,
                user_agent VARCHAR(512) DEFAULT NULL,
                duration_ms INT DEFAULT NULL,
                risk_score NUMERIC(5, 2) DEFAULT NULL,
                anomaly_score NUMERIC(8, 4) DEFAULT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INT DEFAULT NULL,
                metadata JSON DEFAULT NULL,
                created_at DATETIME NOT NULL,
                event_timestamp DATETIME DEFAULT NULL,
                level VARCHAR(20) DEFAULT 'INFO' NOT NULL,
                service_name VARCHAR(100) DEFAULT NULL,
                environment VARCHAR(50) DEFAULT NULL,
                application_version VARCHAR(50) DEFAULT NULL,
                INDEX IDX_event_type_created (event_type, created_at),
                INDEX IDX_event_entity (entity_type, entity_id),
                INDEX IDX_event_user_created (user_id, created_at),
                PRIMARY KEY(id),
                CONSTRAINT FK_app_event_log_user FOREIGN KEY (user_id) REFERENCES user (id_user) ON DELETE SET NULL
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");

            return;
        }

        $table = $schema->getTable('app_event_log');
        $columns = [
            'event_id' => 'ALTER TABLE app_event_log ADD event_id VARCHAR(36) DEFAULT NULL',
            'session_id' => 'ALTER TABLE app_event_log ADD session_id VARCHAR(128) DEFAULT NULL',
            'request_id' => 'ALTER TABLE app_event_log ADD request_id VARCHAR(128) DEFAULT NULL',
            'trace_id' => 'ALTER TABLE app_event_log ADD trace_id VARCHAR(64) DEFAULT NULL',
            'span_id' => 'ALTER TABLE app_event_log ADD span_id VARCHAR(32) DEFAULT NULL',
            'category' => 'ALTER TABLE app_event_log ADD category VARCHAR(50) DEFAULT NULL',
            'action' => 'ALTER TABLE app_event_log ADD action VARCHAR(100) DEFAULT NULL',
            'outcome' => 'ALTER TABLE app_event_log ADD outcome VARCHAR(30) DEFAULT NULL',
            'message' => 'ALTER TABLE app_event_log ADD message LONGTEXT DEFAULT NULL',
            'user_agent' => 'ALTER TABLE app_event_log ADD user_agent VARCHAR(512) DEFAULT NULL',
            'duration_ms' => 'ALTER TABLE app_event_log ADD duration_ms INT DEFAULT NULL',
            'risk_score' => 'ALTER TABLE app_event_log ADD risk_score NUMERIC(5, 2) DEFAULT NULL',
            'anomaly_score' => 'ALTER TABLE app_event_log ADD anomaly_score NUMERIC(8, 4) DEFAULT NULL',
            'event_timestamp' => 'ALTER TABLE app_event_log ADD event_timestamp DATETIME DEFAULT NULL',
            'level' => "ALTER TABLE app_event_log ADD level VARCHAR(20) DEFAULT 'INFO' NOT NULL",
            'service_name' => 'ALTER TABLE app_event_log ADD service_name VARCHAR(100) DEFAULT NULL',
            'environment' => 'ALTER TABLE app_event_log ADD environment VARCHAR(50) DEFAULT NULL',
            'application_version' => 'ALTER TABLE app_event_log ADD application_version VARCHAR(50) DEFAULT NULL',
        ];

        foreach ($columns as $column => $sql) {
            if (!$table->hasColumn($column)) {
                $this->addSql($sql);
            }
        }

        if (!$table->hasIndex('IDX_event_type_created')) {
            $this->addSql('CREATE INDEX IDX_event_type_created ON app_event_log (event_type, created_at)');
        }
        if (!$table->hasIndex('IDX_event_entity')) {
            $this->addSql('CREATE INDEX IDX_event_entity ON app_event_log (entity_type, entity_id)');
        }
        if (!$table->hasIndex('IDX_event_user_created')) {
            $this->addSql('CREATE INDEX IDX_event_user_created ON app_event_log (user_id, created_at)');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('app_event_log')) {
            $this->addSql('DROP TABLE app_event_log');
        }
    }
}

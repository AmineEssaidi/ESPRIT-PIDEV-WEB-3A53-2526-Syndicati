<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create OAUTH table for Gmail (and other) OAuth tokens.
 */
final class Version20260215120000_create_oauth_table extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create OAUTH table for Gmail OAuth token storage';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('OAUTH')) {
            return;
        }

        $this->addSql('CREATE TABLE OAUTH (
            idOAuth INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            access_token LONGTEXT NOT NULL,
            refresh_token LONGTEXT NOT NULL,
            token_type VARCHAR(50) NOT NULL,
            scope LONGTEXT DEFAULT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX IDX_OAUTH_USER (user_id),
            PRIMARY KEY(idOAuth),
            CONSTRAINT FK_OAUTH_USER FOREIGN KEY (user_id) REFERENCES user (id_user) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('OAUTH')) {
            $this->addSql('DROP TABLE OAUTH');
        }
    }
}

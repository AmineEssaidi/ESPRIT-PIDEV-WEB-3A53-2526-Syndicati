<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add two_factor_enabled and totp_secret columns to user table for 2FA.
 * If your table already has different column names (e.g. twoFactorEnabled), rename them to match or run this migration on a copy and align manually.
 */
final class Version20260215000000_add_two_factor_columns extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add two_factor_enabled and totp_secret to user table';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('user');

        if (!$table->hasColumn('two_factor_enabled')) {
            $this->addSql('ALTER TABLE user ADD two_factor_enabled TINYINT(1) DEFAULT 0 NOT NULL');
        }

        if (!$table->hasColumn('totp_secret')) {
            $this->addSql('ALTER TABLE user ADD totp_secret VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->getTable('user')->hasColumn('two_factor_enabled')) {
            $this->addSql('ALTER TABLE user DROP two_factor_enabled');
        }
        if ($schema->getTable('user')->hasColumn('totp_secret')) {
            $this->addSql('ALTER TABLE user DROP totp_secret');
        }
    }
}

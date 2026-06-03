<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260603000000_expand_totp_secret_column extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure user.totp_secret can store authenticator app secrets safely';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('user')) {
            return;
        }

        $table = $schema->getTable('user');
        if ($table->hasColumn('totp_secret')) {
            $this->addSql('ALTER TABLE user MODIFY totp_secret VARCHAR(255) DEFAULT NULL');
        } else {
            $this->addSql('ALTER TABLE user ADD totp_secret VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('user') && $schema->getTable('user')->hasColumn('totp_secret')) {
            $this->addSql('ALTER TABLE user MODIFY totp_secret VARCHAR(32) DEFAULT NULL');
        }
    }
}

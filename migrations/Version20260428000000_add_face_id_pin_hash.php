<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add pin_hash column to facecred table for Face ID PIN verification.
 */
final class Version20260428000000_add_face_id_pin_hash extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pin_hash column to facecred table for Face ID authentication';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('facecred');

        if (!$table->hasColumn('pin_hash')) {
            $this->addSql('ALTER TABLE facecred ADD pin_hash VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('facecred');
        
        if ($table->hasColumn('pin_hash')) {
            $this->addSql('ALTER TABLE facecred DROP pin_hash');
        }
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260201090807 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reponses ADD reclamation_id INT NOT NULL');
        $this->addSql('ALTER TABLE reponses ADD CONSTRAINT FK_1E512EC62D6BA2D9 FOREIGN KEY (reclamation_id) REFERENCES reclamations (id)');
        $this->addSql('CREATE INDEX IDX_1E512EC62D6BA2D9 ON reponses (reclamation_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reponses DROP FOREIGN KEY FK_1E512EC62D6BA2D9');
        $this->addSql('DROP INDEX IDX_1E512EC62D6BA2D9 ON reponses');
        $this->addSql('ALTER TABLE reponses DROP reclamation_id');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260211163434 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE appartement CHANGE type_a type_a ENUM(\'STUDIO\', \'S+1\', \'S+2\', \'S+3\', \'S+4\', \'S+5\')');
        $this->addSql('ALTER TABLE evenement CHANGE statut_event statut_event ENUM(\'planifie\', \'en_cours\', \'termine\', \'annule\'), CHANGE type_event type_event ENUM(\'reunion\', \'social\', \'formation\', \'maintenance\', \'culturel\', \'sportif\')');
        $this->addSql('ALTER TABLE participation CHANGE statut_participation statut_participation ENUM(\'confirme\', \'en_attente\', \'refuse\', \'annule\')');
        $this->addSql('ALTER TABLE reclamations CHANGE statutreclamation statutreclamation ENUM(\'active\', \'en_attente\', \'refuse\', \'termine\') DEFAULT \'en_attente\'');
        $this->addSql('ALTER TABLE residence CHANGE n_blocs n_blocs SET(\'A\', \'B\', \'C\', \'D\', \'E\')');
        $this->addSql('ALTER TABLE user CHANGE role_user role_user ENUM(\'RESIDENT\', \'SYNDIC\', \'OWNER\', \'ADMIN\', \'SUPERADMIN\') DEFAULT \'RESIDENT\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE appartement CHANGE type_a type_a VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE evenement CHANGE statut_event statut_event VARCHAR(255) DEFAULT NULL, CHANGE type_event type_event VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE participation CHANGE statut_participation statut_participation VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE reclamations CHANGE statutreclamation statutreclamation VARCHAR(255) DEFAULT \'en_attente\'');
        $this->addSql('ALTER TABLE residence CHANGE n_blocs n_blocs LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:simple_array)\'');
        $this->addSql('ALTER TABLE user CHANGE role_user role_user VARCHAR(255) DEFAULT \'RESIDENT\'');
    }
}

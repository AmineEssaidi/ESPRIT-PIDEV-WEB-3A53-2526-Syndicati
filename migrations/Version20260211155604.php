<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260211155604 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE appartement DROP FOREIGN KEY FK_71A6BD8D6B3CA4B');
        $this->addSql('ALTER TABLE appartement DROP FOREIGN KEY FK_71A6BD8D8B225FBD');
        $this->addSql('ALTER TABLE appartement CHANGE type_a type_a ENUM(\'STUDIO\', \'S+1\', \'S+2\', \'S+3\', \'S+4\', \'S+5\'), CHANGE appartement_info appartement_info JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE appartement ADD CONSTRAINT FK_71A6BD8D6B3CA4B FOREIGN KEY (id_user) REFERENCES user (id_user) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE appartement ADD CONSTRAINT FK_71A6BD8D8B225FBD FOREIGN KEY (residence_id) REFERENCES residence (id_residence) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE evenement CHANGE statut_event statut_event ENUM(\'planifie\', \'en_cours\', \'termine\', \'annule\'), CHANGE type_event type_event ENUM(\'reunion\', \'social\', \'formation\', \'maintenance\', \'culturel\', \'sportif\')');
        $this->addSql('ALTER TABLE participation CHANGE statut_participation statut_participation ENUM(\'confirme\', \'en_attente\', \'refuse\', \'annule\')');
        $this->addSql('ALTER TABLE reclamations CHANGE statutreclamation statutreclamation ENUM(\'active\', \'en_attente\', \'refuse\', \'termine\') DEFAULT \'en_attente\'');
        $this->addSql('ALTER TABLE reclamations RENAME INDEX fk_1cad6b766b3ca4b TO IDX_1CAD6B766B3CA4B');
        $this->addSql('ALTER TABLE reponses DROP FOREIGN KEY reponses_ibfk_1');
        $this->addSql('ALTER TABLE reponses DROP FOREIGN KEY reponses_ibfk_2');
        $this->addSql('ALTER TABLE reponses ADD CONSTRAINT FK_1E512EC62D6BA2D9 FOREIGN KEY (reclamation_id) REFERENCES reclamations (idreclamations)');
        $this->addSql('ALTER TABLE reponses ADD CONSTRAINT FK_1E512EC66B3CA4B FOREIGN KEY (id_user) REFERENCES user (id_user)');
        $this->addSql('ALTER TABLE reponses RENAME INDEX id_user TO IDX_1E512EC66B3CA4B');
        $this->addSql('ALTER TABLE residence CHANGE n_blocs n_blocs SET(\'A\', \'B\', \'C\', \'D\', \'E\')');
        $this->addSql('ALTER TABLE user CHANGE role_user role_user ENUM(\'RESIDENT\', \'SYNDIC\', \'OWNER\', \'ADMIN\', \'SUPERADMIN\') DEFAULT \'RESIDENT\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE appartement DROP FOREIGN KEY FK_71A6BD8D8B225FBD');
        $this->addSql('ALTER TABLE appartement DROP FOREIGN KEY FK_71A6BD8D6B3CA4B');
        $this->addSql('ALTER TABLE appartement CHANGE type_a type_a VARCHAR(255) DEFAULT NULL, CHANGE appartement_info appartement_info JSON NOT NULL');
        $this->addSql('ALTER TABLE appartement ADD CONSTRAINT FK_71A6BD8D8B225FBD FOREIGN KEY (residence_id) REFERENCES residence (id_residence) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE appartement ADD CONSTRAINT FK_71A6BD8D6B3CA4B FOREIGN KEY (id_user) REFERENCES user (id_user) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE evenement CHANGE statut_event statut_event VARCHAR(255) DEFAULT NULL, CHANGE type_event type_event VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE participation CHANGE statut_participation statut_participation VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE reclamations CHANGE statutreclamation statutreclamation VARCHAR(255) DEFAULT \'en_attente\'');
        $this->addSql('ALTER TABLE reclamations RENAME INDEX idx_1cad6b766b3ca4b TO FK_1CAD6B766B3CA4B');
        $this->addSql('ALTER TABLE reponses DROP FOREIGN KEY FK_1E512EC62D6BA2D9');
        $this->addSql('ALTER TABLE reponses DROP FOREIGN KEY FK_1E512EC66B3CA4B');
        $this->addSql('ALTER TABLE reponses ADD CONSTRAINT reponses_ibfk_1 FOREIGN KEY (reclamation_id) REFERENCES reclamations (idreclamations) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reponses ADD CONSTRAINT reponses_ibfk_2 FOREIGN KEY (id_user) REFERENCES user (id_user) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reponses RENAME INDEX idx_1e512ec66b3ca4b TO id_user');
        $this->addSql('ALTER TABLE residence CHANGE n_blocs n_blocs LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:simple_array)\'');
        $this->addSql('ALTER TABLE user CHANGE role_user role_user VARCHAR(255) DEFAULT \'RESIDENT\'');
    }
}

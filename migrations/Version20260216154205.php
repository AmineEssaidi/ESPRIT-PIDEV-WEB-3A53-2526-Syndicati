<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260216154205 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE publication_bookmark (id_bookmark INT AUTO_INCREMENT NOT NULL, id_publication INT NOT NULL, id_user INT NOT NULL, bookmark TINYINT(1) NOT NULL, INDEX IDX_F505674CB72EAA8E (id_publication), INDEX IDX_F505674C6B3CA4B (id_user), PRIMARY KEY(id_bookmark)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE publication_report (id_report INT AUTO_INCREMENT NOT NULL, id_publication INT NOT NULL, id_user INT NOT NULL, `signal` TINYINT(1) NOT NULL, INDEX IDX_20C466C9B72EAA8E (id_publication), INDEX IDX_20C466C96B3CA4B (id_user), PRIMARY KEY(id_report)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE publication_bookmark ADD CONSTRAINT FK_F505674CB72EAA8E FOREIGN KEY (id_publication) REFERENCES publication (id)');
        $this->addSql('ALTER TABLE publication_bookmark ADD CONSTRAINT FK_F505674C6B3CA4B FOREIGN KEY (id_user) REFERENCES user (id_user)');
        $this->addSql('ALTER TABLE publication_report ADD CONSTRAINT FK_20C466C9B72EAA8E FOREIGN KEY (id_publication) REFERENCES publication (id)');
        $this->addSql('ALTER TABLE publication_report ADD CONSTRAINT FK_20C466C96B3CA4B FOREIGN KEY (id_user) REFERENCES user (id_user)');
        $this->addSql('ALTER TABLE facecred DROP FOREIGN KEY facecred_ibfk_1');
        $this->addSql('ALTER TABLE webauthncred DROP FOREIGN KEY webauthncred_ibfk_1');
        $this->addSql('DROP TABLE facecred');
        $this->addSql('DROP TABLE oauth');
        $this->addSql('DROP TABLE webauthncred');
        $this->addSql('ALTER TABLE appartement DROP FOREIGN KEY FK_71A6BD8D6B3CA4B');
        $this->addSql('ALTER TABLE appartement DROP FOREIGN KEY FK_71A6BD8D8B225FBD');
        $this->addSql('ALTER TABLE appartement CHANGE type_a type_a ENUM(\'STUDIO\', \'S+1\', \'S+2\', \'S+3\', \'S+4\', \'S+5\'), CHANGE appartement_info appartement_info JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE appartement ADD CONSTRAINT FK_71A6BD8D6B3CA4B FOREIGN KEY (id_user) REFERENCES user (id_user) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE appartement ADD CONSTRAINT FK_71A6BD8D8B225FBD FOREIGN KEY (residence_id) REFERENCES residence (id_residence) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE evenement CHANGE statut_event statut_event ENUM(\'planifie\', \'en_cours\', \'termine\', \'annule\'), CHANGE type_event type_event ENUM(\'reunion\', \'social\', \'formation\', \'maintenance\', \'culturel\', \'sportif\')');
        $this->addSql('ALTER TABLE participation CHANGE statut_participation statut_participation ENUM(\'confirme\', \'en_attente\', \'refuse\', \'annule\')');
        $this->addSql('DROP INDEX id_publication ON pubreaction');
        $this->addSql('ALTER TABLE pubreaction CHANGE reaction_type reaction_type ENUM(\'like\', \'dislike\')');
        $this->addSql('CREATE INDEX IDX_36A5DC7FB72EAA8E ON pubreaction (id_publication)');
        $this->addSql('ALTER TABLE pubreaction RENAME INDEX id_user TO IDX_36A5DC7F6B3CA4B');
        $this->addSql('ALTER TABLE reclamations CHANGE statutreclamation statutreclamation ENUM(\'active\', \'en_attente\', \'refuse\', \'termine\') DEFAULT \'en_attente\'');
        $this->addSql('ALTER TABLE reclamations RENAME INDEX fk_1cad6b766b3ca4b TO IDX_1CAD6B766B3CA4B');
        $this->addSql('ALTER TABLE reponses DROP FOREIGN KEY reponses_ibfk_1');
        $this->addSql('ALTER TABLE reponses DROP FOREIGN KEY reponses_ibfk_2');
        $this->addSql('ALTER TABLE reponses ADD CONSTRAINT FK_1E512EC62D6BA2D9 FOREIGN KEY (reclamation_id) REFERENCES reclamations (idreclamations)');
        $this->addSql('ALTER TABLE reponses ADD CONSTRAINT FK_1E512EC66B3CA4B FOREIGN KEY (id_user) REFERENCES user (id_user)');
        $this->addSql('ALTER TABLE reponses RENAME INDEX id_user TO IDX_1E512EC66B3CA4B');
        $this->addSql('ALTER TABLE residence CHANGE n_blocs n_blocs SET(\'A\', \'B\', \'C\', \'D\', \'E\')');
        $this->addSql('ALTER TABLE user DROP authCode, DROP authCode_expires_at, DROP two_factor_enabled, DROP totp_secret, CHANGE role_user role_user ENUM(\'RESIDENT\', \'SYNDIC\', \'OWNER\', \'ADMIN\', \'SUPERADMIN\') DEFAULT \'RESIDENT\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE facecred (id_facecred INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, device_id VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, encrypted_faceid BLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, last_used_at DATETIME NOT NULL, flag LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'(DC2Type:simple_array)\', INDEX user_id (user_id), PRIMARY KEY(id_facecred)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE oauth (idOAuth INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, access_token TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, refresh_token TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, token_type VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, scope TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, expires_at DATETIME NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(idOAuth)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE webauthncred (id_webauthn INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, credential_id VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, public_key TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, sign_count INT NOT NULL, transports JSON NOT NULL, created_at DATETIME NOT NULL, last_used_at DATETIME NOT NULL, INDEX user_id (user_id), PRIMARY KEY(id_webauthn)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE facecred ADD CONSTRAINT facecred_ibfk_1 FOREIGN KEY (user_id) REFERENCES user (id_user) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE webauthncred ADD CONSTRAINT webauthncred_ibfk_1 FOREIGN KEY (user_id) REFERENCES user (id_user) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE publication_bookmark DROP FOREIGN KEY FK_F505674CB72EAA8E');
        $this->addSql('ALTER TABLE publication_bookmark DROP FOREIGN KEY FK_F505674C6B3CA4B');
        $this->addSql('ALTER TABLE publication_report DROP FOREIGN KEY FK_20C466C9B72EAA8E');
        $this->addSql('ALTER TABLE publication_report DROP FOREIGN KEY FK_20C466C96B3CA4B');
        $this->addSql('DROP TABLE publication_bookmark');
        $this->addSql('DROP TABLE publication_report');
        $this->addSql('ALTER TABLE appartement DROP FOREIGN KEY FK_71A6BD8D8B225FBD');
        $this->addSql('ALTER TABLE appartement DROP FOREIGN KEY FK_71A6BD8D6B3CA4B');
        $this->addSql('ALTER TABLE appartement CHANGE type_a type_a VARCHAR(255) DEFAULT NULL, CHANGE appartement_info appartement_info JSON NOT NULL');
        $this->addSql('ALTER TABLE appartement ADD CONSTRAINT FK_71A6BD8D8B225FBD FOREIGN KEY (residence_id) REFERENCES residence (id_residence) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE appartement ADD CONSTRAINT FK_71A6BD8D6B3CA4B FOREIGN KEY (id_user) REFERENCES user (id_user) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE evenement CHANGE statut_event statut_event VARCHAR(255) DEFAULT NULL, CHANGE type_event type_event VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE participation CHANGE statut_participation statut_participation VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE pubreaction DROP FOREIGN KEY FK_36A5DC7FB72EAA8E');
        $this->addSql('ALTER TABLE pubreaction DROP FOREIGN KEY FK_36A5DC7F6B3CA4B');
        $this->addSql('DROP INDEX IDX_36A5DC7FB72EAA8E ON pubreaction');
        $this->addSql('ALTER TABLE pubreaction CHANGE reaction_type reaction_type VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX id_publication ON pubreaction (id_publication, id_user)');
        $this->addSql('ALTER TABLE pubreaction RENAME INDEX idx_36a5dc7f6b3ca4b TO id_user');
        $this->addSql('ALTER TABLE reclamations CHANGE statutreclamation statutreclamation VARCHAR(255) DEFAULT \'en_attente\'');
        $this->addSql('ALTER TABLE reclamations RENAME INDEX idx_1cad6b766b3ca4b TO FK_1CAD6B766B3CA4B');
        $this->addSql('ALTER TABLE reponses DROP FOREIGN KEY FK_1E512EC62D6BA2D9');
        $this->addSql('ALTER TABLE reponses DROP FOREIGN KEY FK_1E512EC66B3CA4B');
        $this->addSql('ALTER TABLE reponses ADD CONSTRAINT reponses_ibfk_1 FOREIGN KEY (reclamation_id) REFERENCES reclamations (idreclamations) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reponses ADD CONSTRAINT reponses_ibfk_2 FOREIGN KEY (id_user) REFERENCES user (id_user) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reponses RENAME INDEX idx_1e512ec66b3ca4b TO id_user');
        $this->addSql('ALTER TABLE residence CHANGE n_blocs n_blocs LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:simple_array)\'');
        $this->addSql('ALTER TABLE user ADD authCode VARCHAR(50) DEFAULT NULL, ADD authCode_expires_at DATETIME NOT NULL, ADD two_factor_enabled TINYINT(1) DEFAULT 0 NOT NULL, ADD totp_secret VARCHAR(255) DEFAULT NULL, CHANGE role_user role_user VARCHAR(255) DEFAULT \'RESIDENT\'');
    }
}

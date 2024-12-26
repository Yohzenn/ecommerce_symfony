<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241226112537 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE panier DROP INDEX UNIQ_24CC0DF2FB88E14F, ADD INDEX IDX_24CC0DF2FB88E14F (utilisateur_id)');
        $this->addSql('ALTER TABLE panier CHANGE utilisateur_id utilisateur_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE panier DROP INDEX IDX_24CC0DF2FB88E14F, ADD UNIQUE INDEX UNIQ_24CC0DF2FB88E14F (utilisateur_id)');
        $this->addSql('ALTER TABLE panier CHANGE utilisateur_id utilisateur_id INT DEFAULT NULL');
    }
}

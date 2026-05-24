<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260511103342 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE club CHANGE description description LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE feedback CHANGE id id INT AUTO_INCREMENT NOT NULL, ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE feedback ADD CONSTRAINT FK_D2294458A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE feedback ADD CONSTRAINT FK_D229445871F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('CREATE INDEX IDX_D2294458A76ED395 ON feedback (user_id)');
        $this->addSql('CREATE INDEX IDX_D229445871F7E88B ON feedback (event_id)');
        $this->addSql('ALTER TABLE participation CHANGE id id INT AUTO_INCREMENT NOT NULL');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24F71F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE reclamation CHANGE id id INT AUTO_INCREMENT NOT NULL');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT FK_CE606404A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE recruitment CHANGE id id INT AUTO_INCREMENT NOT NULL');
        $this->addSql('ALTER TABLE recruitment ADD CONSTRAINT FK_C7238C6E61190A32 FOREIGN KEY (club_id) REFERENCES club (id)');
        $this->addSql('ALTER TABLE messenger_messages CHANGE id id BIGINT AUTO_INCREMENT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE club CHANGE description description LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE feedback DROP FOREIGN KEY FK_D2294458A76ED395');
        $this->addSql('ALTER TABLE feedback DROP FOREIGN KEY FK_D229445871F7E88B');
        $this->addSql('DROP INDEX IDX_D2294458A76ED395 ON feedback');
        $this->addSql('DROP INDEX IDX_D229445871F7E88B ON feedback');
        $this->addSql('ALTER TABLE feedback MODIFY id INT NOT NULL');
        $this->addSql('ALTER TABLE feedback CHANGE id id INT NOT NULL, DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE messenger_messages CHANGE id id BIGINT NOT NULL');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24FA76ED395');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24F71F7E88B');
        $this->addSql('ALTER TABLE participation CHANGE id id INT NOT NULL');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY FK_CE606404A76ED395');
        $this->addSql('ALTER TABLE reclamation CHANGE id id INT NOT NULL');
        $this->addSql('ALTER TABLE recruitment DROP FOREIGN KEY FK_C7238C6E61190A32');
        $this->addSql('ALTER TABLE recruitment CHANGE id id INT NOT NULL');
    }
}

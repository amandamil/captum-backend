<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190929123907 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE subscriptions ADD next_plan_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE subscriptions ADD CONSTRAINT FK_4778A012A172839 FOREIGN KEY (next_plan_id) REFERENCES packages (id)');
        $this->addSql('CREATE INDEX IDX_4778A012A172839 ON subscriptions (next_plan_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE subscriptions DROP FOREIGN KEY FK_4778A012A172839');
        $this->addSql('DROP INDEX IDX_4778A012A172839 ON subscriptions');
        $this->addSql('ALTER TABLE subscriptions DROP next_plan_id');
    }
}

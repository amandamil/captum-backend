<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190709072957 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE subscriptions ADD apple_order_line_item_id VARCHAR(255) DEFAULT NULL, ADD provider_type VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE TABLE clients (id INT AUTO_INCREMENT NOT NULL, random_id VARCHAR(255) NOT NULL, secret VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE api_token ADD scope VARCHAR(255) DEFAULT NULL, ADD client_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE api_token ADD CONSTRAINT FK_7BA2F5EB19EB6921 FOREIGN KEY (client_id) REFERENCES clients (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7BA2F5EB19EB6921 ON api_token (client_id)');
        $this->addSql('CREATE INDEX user_scope_client_idx ON api_token (user_id, scope, client_id)');
        $this->addSql('CREATE INDEX public_id_idx ON clients (id, random_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('DROP INDEX user_scope_client_idx ON api_token');
        $this->addSql('DROP INDEX public_id_idx ON clients');
        $this->addSql('ALTER TABLE api_token DROP FOREIGN KEY FK_7BA2F5EB19EB6921');
        $this->addSql('DROP TABLE clients');
        $this->addSql('DROP INDEX UNIQ_7BA2F5EB19EB6921 ON api_token');
        $this->addSql('ALTER TABLE api_token DROP scope, DROP client_id');
        $this->addSql('ALTER TABLE subscriptions DROP apple_order_line_item_id, DROP provider_type');
    }
}

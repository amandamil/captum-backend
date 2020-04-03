<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190715120920 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE transactions CHANGE braintree_transaction_id transaction_id VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE packages ADD apple_product_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql','Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE packages DROP apple_product_id');
        $this->addSql('ALTER TABLE transactions CHANGE transaction_id braintree_transaction_id VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci');
    }
}

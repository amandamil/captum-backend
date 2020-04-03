<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190929134148 extends AbstractMigration
{
    /** @var int|null $trialPlanId */
    private $trialPlanId = null;

    public function preUp(Schema $schema)
    {
        parent::preUp($schema);
        $this->trialPlanId = $this->connection->fetchAll(
            'SELECT `packages`.id 
                    FROM `packages` 
                    WHERE `packages`.is_trial = 1 
                        AND `packages`.braintree_plan_id IS NULL 
                        AND `packages`.apple_product_id IS NULL
                        AND `packages`.price = 0 LIMIT 1'
        )[0]['id'];
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql(
            "UPDATE packages
                    SET expires_in_months = 2,
                        experiences_number = 3,
                        recognitions_number = 3000,
                        description = 'Free 2 months trial'
                    WHERE id = ".(int)$this->trialPlanId
        );
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql(
            "UPDATE packages
                    SET expires_in_months = 0,
                        experiences_number = 1,
                        recognitions_number = 100,
                        description = 'Free 1 day trial'
                    WHERE id = ".$this->trialPlanId
        );
    }
}

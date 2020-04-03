<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use SubscriptionBundle\Entity\Package;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190519102304 extends AbstractMigration
{
    /** @var array $oldTrialPackage */
    private $oldTrialPackage = [];

    public function preUp(Schema $schema)
    {
        parent::preUp($schema);
        $this->oldTrialPackage = $this->connection->fetchAll('SELECT * FROM `packages` WHERE `packages`.is_trial = TRUE');
    }

    public function up(Schema $schema) : void
    {
        foreach ($this->oldTrialPackage as $item) {
            $this->addSql("UPDATE packages SET recognitions_number = 100 WHERE id = '".$item['id']."'");
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}

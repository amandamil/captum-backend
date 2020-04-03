<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use SubscriptionBundle\Entity\Package;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191029182813 extends AbstractMigration
{
    /** @var array $packages */
    private $packages = [];

    public function preUp(Schema $schema)
    {
        parent::preUp($schema);
        $this->packages = $this->connection->fetchAll(
            "SELECT `packages`.* FROM `packages`
                    WHERE `packages`.is_public = 1 AND `packages`.is_trial = 0");

    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        /** @var Package $package */
        foreach ($this->packages as $package) {
            switch ($package['price']) {
                case 2000:
                    $this->addSql(
                        "UPDATE packages
                                SET apple_product_id = 'com.umbrellait.CaptumApp.one.experience'
                                WHERE id = ".$package['id']
                    );
                    break;
                case 4500:
                    $this->addSql(
                        "UPDATE packages
                                SET apple_product_id = 'com.umbrellait.CaptumApp.three.experience'
                                WHERE id = ".$package['id']
                    );
                    break;
                case 6000:
                    $this->addSql(
                        "UPDATE packages
                                SET apple_product_id = 'com.umbrellait.CaptumApp.five.experience'
                                WHERE id = ".$package['id']
                    );
                    break;
                case 10000:
                    $this->addSql(
                        "UPDATE packages
                                SET apple_product_id = 'com.umbrellait.CaptumApp.ten.experience'
                                WHERE id = ".$package['id']
                    );
                    break;
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        foreach ($this->packages as $package) {
            $this->addSql(
                "UPDATE packages
                    SET apple_product_id = NULL
                    WHERE id = ".$package['id']
            );
        }
    }
}

<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190424162211 extends AbstractMigration
{
    /** @var array $oldExperiences */
    private $oldExperiences = [];

    public function preUp(Schema $schema)
    {
        parent::preUp($schema);
        $this->oldExperiences = $this->connection->fetchAll(
            'SELECT 
                    `experience`.*, 
                    `image`.url AS image_url,
                    `image`.aws_key AS aws_image_key,
                    `video`.aws_key AS aws_video_key,
                    `video`.transcoded_url_hd,
                    `video`.transcoded_url_full_hd,
                    `video`.transcoded_url_hdkey,
                    `video`.transcoded_url_full_hdkey
                    FROM `experience` 
                    LEFT JOIN `image` ON `experience`.image_id = `image`.id 
                    LEFT JOIN `video` ON `experience`.video_id = `video`.id');
    }

    /**
     * @param Schema $schema
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\DBAL\Migrations\AbortMigrationException
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE experience ADD aws_image_key VARCHAR(255) NOT NULL, ADD image_url VARCHAR(255) NOT NULL, ADD aws_video_key VARCHAR(255) NOT NULL, ADD transcoded_url_hd VARCHAR(255) DEFAULT NULL, ADD transcoded_url_full_hd VARCHAR(255) DEFAULT NULL, ADD transcoded_url_hdkey VARCHAR(255) DEFAULT NULL, ADD transcoded_url_full_hdkey VARCHAR(255) DEFAULT NULL');
        foreach ($this->oldExperiences as $oldExperience) {
            $this->addSql(
                "UPDATE experience 
                      SET image_url = '".$oldExperience['image_url']."',
                      aws_image_key = '".$oldExperience['aws_image_key']."',
                      aws_video_key = '".$oldExperience['aws_video_key']."',
                      transcoded_url_hd = '".$oldExperience['transcoded_url_hd']."',
                      transcoded_url_full_hd = '".$oldExperience['transcoded_url_full_hd']."',
                      transcoded_url_hdkey = '".$oldExperience['transcoded_url_hdkey']."',
                      transcoded_url_full_hdkey = '".$oldExperience['transcoded_url_full_hdkey']."'
                      WHERE id = '".$oldExperience['id']."'
                      ");
        }

        $this->addSql('ALTER TABLE experience DROP FOREIGN KEY FK_590C10329C1004E');
        $this->addSql('ALTER TABLE experience DROP FOREIGN KEY FK_590C1033DA5256D');
        $this->addSql('DROP INDEX UNIQ_590C10329C1004E ON experience');
        $this->addSql('DROP INDEX UNIQ_590C1033DA5256D ON experience');
        $this->addSql('ALTER TABLE experience DROP video_id, DROP image_id');
        $this->addSql('CREATE INDEX token_idx ON api_token (token)');
        $this->addSql('CREATE INDEX status_idx ON experience (status)');
        $this->addSql('CREATE INDEX actual_code_email_idx ON verification_codes (code, email, used, status, type, sent_at)');
    }

    /**
     * @param Schema $schema
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\DBAL\Migrations\AbortMigrationException
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE experience ADD video_id INT DEFAULT NULL, ADD image_id INT DEFAULT NULL, DROP aws_image_key, DROP image_url, DROP aws_video_key, DROP transcoded_url_hd, DROP transcoded_url_full_hd, DROP transcoded_url_hdkey, DROP transcoded_url_full_hdkey');
        $this->addSql('ALTER TABLE experience ADD CONSTRAINT FK_590C10329C1004E FOREIGN KEY (video_id) REFERENCES video (id)');
        $this->addSql('ALTER TABLE experience ADD CONSTRAINT FK_590C1033DA5256D FOREIGN KEY (image_id) REFERENCES image (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_590C10329C1004E ON experience (video_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_590C1033DA5256D ON experience (image_id)');
        $this->addSql('DROP INDEX token_idx ON api_token');
        $this->addSql('DROP INDEX status_idx ON experience');
        $this->addSql('DROP INDEX actual_code_email_idx ON verification_codes');
    }
}

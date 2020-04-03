<?php

namespace ExperienceBundle\Command;

use ExperienceBundle\Services\ExperienceService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DeleteExperienceMediaCommand
 * @package ExperienceBundle\Command
 */
class DeleteExperienceMediaCommand extends ContainerAwareCommand
{
    const NAME = 'experience:delete_experience_media_command';

    /** @var ExperienceService $experienceService */
    private $experienceService;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName(self::NAME)
            ->setDescription('Command delete media files for deleted experiences');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        $this->experienceService = $this->getContainer()->get('experience.service.experience');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Run delete experiences media');

        $ids = $this->experienceService->deleteMediaForDeletedExperiences();
        foreach ($ids as $id) {
            $output->writeln('Deleted experiences: '.$id);
        }

        $output->writeln('Done delete experiences media');
    }
}

<?php

namespace ExperienceBundle\Command;

use ExperienceBundle\Services\ExperienceService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DeleteRejectedExperiencesCommand
 * @package ExperienceBundle\Command
 */
class DeleteRejectedExperiencesCommand extends ContainerAwareCommand
{
    const NAME = 'experience:delete_rejected_experiences_command';

    /** @var ExperienceService $experienceService */
    private $experienceService;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName(self::NAME);
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
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Run reject processing experiences job');

        $ids = $this->experienceService->deleteRejectedExperiences();
        foreach ($ids as $id) {
            $output->writeln('Deleted experiences: '.$id);
        }

        $output->writeln('Done reject processing experiences job');
    }
}

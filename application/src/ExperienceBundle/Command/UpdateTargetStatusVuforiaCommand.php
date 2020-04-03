<?php

namespace ExperienceBundle\Command;

use ExperienceBundle\Services\ExperienceService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class UpdateTargetStatusVuforiaCommand
 * @package ExperienceBundle\Command
 */
class UpdateTargetStatusVuforiaCommand extends ContainerAwareCommand
{
    const NAME = 'update:status:target:vuforia';
    const EXPERIENCE_OBJECT_ID = 'experience-object-id';

    /** @var ExperienceService $experienceService */
    private $experienceService;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName(self::NAME)
            ->addOption(self::EXPERIENCE_OBJECT_ID, 'expId', InputOption::VALUE_REQUIRED, 'Experience ID');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
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
        $output->writeln('Run vuforia target sending');

        /** @var integer $experienceId */
        $experienceId = $input->getOption(self::EXPERIENCE_OBJECT_ID);

        $this->experienceService->updateTargetStatus($experienceId);

        $output->writeln('Done vuforia target sending');
    }
}

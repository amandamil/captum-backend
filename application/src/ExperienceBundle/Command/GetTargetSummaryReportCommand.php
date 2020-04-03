<?php

namespace ExperienceBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use ExperienceBundle\Services\ExperienceService;
use Symfony\Component\Console\ { Input\InputInterface, Input\InputOption, Output\OutputInterface };

/**
 * Class GetTargetSummaryReportCommand
 * @package ExperienceBundle\Command
 */
class GetTargetSummaryReportCommand extends ContainerAwareCommand
{
    const NAME = 'get:target:summary:report:command';
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
     * initialize
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    public function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        $this->experienceService = $this->getContainer()->get('experience.service.experience');
    }

    /**
     * execute command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function execute(InputInterface $input, OutputInterface $output) 
    {
        $output->writeln('Run get target summary report');

        /** @var integer $experienceId */
        $experienceId = $input->getOption(self::EXPERIENCE_OBJECT_ID);

        $this->experienceService->updateRecognitionStatistic($experienceId);

        $output->writeln('Done target summary report');
    }
}
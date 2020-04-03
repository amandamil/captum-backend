<?php

namespace ExperienceBundle\Command;

use ExperienceBundle\Services\ExperienceService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\{
    Input\InputInterface,
    Input\InputOption,
    Output\OutputInterface
};

/**
 * Class CreateTranscoderJobsCommand
 * @package ExperienceBundle\Command
 */
class CreateTranscoderJobsCommand extends ContainerAwareCommand
{
    const NAME = 'experience:create:transcoder:jobs';
    const EXPERIENCE_OBJECT_ID = 'experience-object-id';
    const ROTATION = 'rotation';
    const FILENAME = 'file-name';
    const WIDTH = 'width';
    const HEIGHT = 'height';

    /** @var ExperienceService $experienceService */
    private $experienceService;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName(self::NAME)
            ->addOption(self::EXPERIENCE_OBJECT_ID, 'expId', InputOption::VALUE_REQUIRED, 'Experience ID')
            ->addOption(self::ROTATION, 'rotation', InputOption::VALUE_REQUIRED, 'Rotation degree')
            ->addOption(self::FILENAME, 'fileName', InputOption::VALUE_REQUIRED, 'file name')
            ->addOption(self::WIDTH, 'width', InputOption::VALUE_REQUIRED, 'width')
            ->addOption(self::HEIGHT, 'height', InputOption::VALUE_REQUIRED, 'height');
    }

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
        $output->writeln('Run creating transcoder jobs');

        /** @var integer $experienceId */
        $experienceId = $input->getOption(self::EXPERIENCE_OBJECT_ID);

        /** @var integer $rotation */
        $rotation = $input->getOption(self::ROTATION);

        /** @var integer $width */
        $width = $input->getOption(self::WIDTH);

        /** @var integer $height */
        $height = $input->getOption(self::HEIGHT);

        /** @var string $fileName */
        $fileName = $input->getOption(self::FILENAME);

        $this->experienceService->createExperienceTranscoderJob($experienceId, $fileName, $rotation, $width, $height);

        $output->writeln('Done creating transcoder jobs');
    }
}

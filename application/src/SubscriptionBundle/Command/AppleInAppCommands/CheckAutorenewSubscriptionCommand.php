<?php

namespace SubscriptionBundle\Command\AppleInAppCommands;

use SubscriptionBundle\Services\SubscriptionService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\{
    Input\InputInterface,
    Input\InputOption,
    Output\OutputInterface
};

/**
 * Class CheckAutorenewSubscriptionCommand
 * @package SubscriptionBundle\Command\AppleInAppCommands
 */
class CheckAutorenewSubscriptionCommand extends ContainerAwareCommand
{
    const NAME = 'apple:check_autorenew_subscription_command';
    const SUBSCRIPTION_OBJECT_ID = 'subscription-object-id';

    /** @var SubscriptionService $subscriptionService */
    private $subscriptionService;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName(self::NAME)
            ->addOption(
                self::SUBSCRIPTION_OBJECT_ID,
                'subsId',
                InputOption::VALUE_REQUIRED,
                'Subscription ID'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        $this->subscriptionService = $this->getContainer()->get('subscription.service.subscription');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Money\UnknownCurrencyException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Run check autorenew subscription');

        /** @var integer $subscriptionId */
        $subscriptionId = $input->getOption(self::SUBSCRIPTION_OBJECT_ID);

        $this->subscriptionService->checkAppleAutorenewSubscription($subscriptionId);

        $output->writeln('Done check autorenew subscription');
    }
}

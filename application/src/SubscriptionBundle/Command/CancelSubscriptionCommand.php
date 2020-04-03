<?php

namespace SubscriptionBundle\Command;

use SubscriptionBundle\Services\SubscriptionService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CancelSubscriptionCommand
 * @package SubscriptionBundle\Command
 */
class CancelSubscriptionCommand extends ContainerAwareCommand
{
    const NAME = 'subscription:cancel_subscription_command';
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
            ->addOption(self::SUBSCRIPTION_OBJECT_ID, 'subscriptionId', InputOption::VALUE_REQUIRED, 'Subscription ID');
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
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Run subscription cancel');

        /** @var integer $subscriptionId */
        $subscriptionId = $input->getOption(self::SUBSCRIPTION_OBJECT_ID);

        $this->subscriptionService->disableSubscription($subscriptionId);

        $output->writeln('Done subscription cancel');
    }
}

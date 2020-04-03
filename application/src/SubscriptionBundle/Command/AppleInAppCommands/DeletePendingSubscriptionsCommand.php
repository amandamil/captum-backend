<?php

namespace SubscriptionBundle\Command\AppleInAppCommands;

use SubscriptionBundle\Services\SubscriptionService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DeletePendingSubscriptionsCommand
 * @package SubscriptionBundle\Command\AppleInAppCommands
 */
class DeletePendingSubscriptionsCommand extends ContainerAwareCommand
{
    const NAME = 'apple:delete_pending_subscriptions_command';

    /** @var SubscriptionService $subscriptionService */
    private $subscriptionService;

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
        $this->subscriptionService = $this->getContainer()->get('subscription.service.subscription');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Run delete pending subscriptions job');

        $this->subscriptionService->deleteRejectedExperiences();

        $output->writeln('Done delete pending subscriptions job');
    }
}

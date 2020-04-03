<?php

namespace SubscriptionBundle\Command;

use SubscriptionBundle\Services\SubscriptionService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AutorenewSubscriptionChargeNotificationCommand
 * @package SubscriptionBundle\Command
 */
class AutorenewSubscriptionChargeNotificationCommand extends ContainerAwareCommand
{
    const NAME = 'experience:autorenew_subscription_charge_notification_command';
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
            ->addOption(self::SUBSCRIPTION_OBJECT_ID,'subsId',InputOption::VALUE_REQUIRED,'Subscription ID');
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
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Run creating autorenew notification');

        /** @var integer $subscriptionId */
        $subscriptionId = $input->getOption(self::SUBSCRIPTION_OBJECT_ID);

        $this->subscriptionService->autorenewChargeNotification($subscriptionId);

        $output->writeln('Done creating autorenew notification');
    }
}

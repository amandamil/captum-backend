<?php

namespace UserApiBundle\Services;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UserApiBundle\Entity\Balance;
use UserApiBundle\Entity\Device;
use UserApiBundle\Entity\User;

/**
 * Class NotificationService
 * @package UserApiBundle\Services
 */
class NotificationService
{
    /** @var EntityManager */
    private $em;

    /** @var ContainerInterface $container */
    private $container;

    /** @var DeviceService $deviceService */
    private $deviceService;

    /**
     * NotificationService constructor.
     * @param ContainerInterface $container
     * @param EntityManagerInterface $em
     * @param DeviceService $deviceService
     */
    public function __construct(
        ContainerInterface $container,
        EntityManagerInterface $em,
        DeviceService $deviceService
    )
    {
        $this->em = $em;
        $this->container = $container;
        $this->deviceService = $deviceService;
    }

    /**
     * @param User $user
     * @param Balance|null $balance
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Money\UnknownCurrencyException
     */
    public function sendFreeViewsPercentReached(User $user, ?Balance $balance = null): void
    {
        $title = is_null($balance) || ($balance && $balance->getAmount()->getAmount() <= 0)
            ? 'Refill your balance to continue promotions!'
            : 'Make sure you have enough on your balance!';

        $this->deviceService->sendPushNotificationByUser(
            $user,
            'Your now have only 20% left of your free view limit',
            $title,
            ['pushType' => Device::PUSH_TYPE_PAYMENT]
        );
    }

    /**
     * @param User $user
     * @param Balance|null $balance
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Money\UnknownCurrencyException
     */
    public function sendFreeViewsReached(User $user, ?Balance $balance = null): void
    {
        $title = is_null($balance) || ($balance && $balance->getAmount()->getAmount() <= 0)
            ? 'Refill your balance to continue promotions!'
            : 'Make sure you have enough on your balance!';

        $this->deviceService->sendPushNotificationByUser(
            $user,
            'Free views ended for this month',
            $title,
            ['pushType' => Device::PUSH_TYPE_PAYMENT]
        );
    }

    /**
     * @param User $user
     * @param Balance|null $balance
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function sendLimitOrBalanceReached(User $user, ?Balance $balance = null): void
    {
        $title = $balance->isChargeLimitEnabled() ? 'Limit reached' : 'Balance reached';
        $message = $balance->isChargeLimitEnabled()
            ? 'Your limit was reached. Extend the limit to continue your promotions!'
            : 'Your balance was reached. Refill balance to continue your promotions!';

        $this->deviceService->sendPushNotificationByUser(
            $user,
            $title,
            $message,
            ['pushType' => Device::PUSH_TYPE_PAYMENT]
        );
    }

    /**
     * @param User $user
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function sendLimitPercentReached(User $user): void
    {
        $this->deviceService->sendPushNotificationByUser(
            $user,
            'Your now have only 20% left of your monthly limit',
            'Extend the limit to continue your promotions!',
            ['pushType' => Device::PUSH_TYPE_PAYMENT]
        );
    }

    /**
     * @param User $user
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function sendBalancePercentReached(User $user): void
    {
        $this->deviceService->sendPushNotificationByUser(
            $user,
            'Your balance is now 10% from the last refilling',
            'Refill balance to continue your promotions!',
            ['pushType' => Device::PUSH_TYPE_PAYMENT]
        );
    }

    /**
     * @param User $user
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function sendSubscriptionAutorenewFailed(User $user): void
    {
        $this->deviceService->sendPushNotificationByUser(
            $user,
            'Subscription autorenew failed',
            'We were unable to autorenew your subscription. Please check you payment method',
            ['pushType' => Device::PUSH_TYPE_PAYMENT]
        );
    }

    /**
     * @param User $user
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function sendSubscriptionExpired(User $user): void
    {
        $this->deviceService->sendPushNotificationByUser(
            $user,
            'Subscription expired',
            'The cancelled subscription expired. You can renew your subscription by purchasing it again.',
            ['pushType' => Device::PUSH_TYPE_PAYMENT]
        );
    }

    /**
     * @param User $user
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function sendSubscriptionAutorenewChargeFailed(User $user): void
    {
        $this->deviceService->sendPushNotificationByUser(
            $user,
            'Auto-renew subscription failed',
            'Auto-renew subscription failed, charge failed',
            ['pushType' => Device::PUSH_TYPE_PAYMENT]
        );
    }

    /**
     * @param User $user
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function sendSubscriptionAutorenew(User $user): void
    {
        $this->deviceService->sendPushNotificationByUser(
            $user,
            'Subscription autorenewed',
            'Subscription autorenewed',
            ['pushType' => Device::PUSH_TYPE_PAYMENT]
        );
    }

    /**
     * @param User $user
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function sendSubscriptionTrialExpired(User $user): void
    {
        $this->deviceService->sendPushNotificationByUser(
            $user,
            'Subscription trial period has expired',
            'Your free trial plan has expired. Please subscribe to a paid one',
            ['pushType' => Device::PUSH_TYPE_PAYMENT]
        );
    }

    /**
     * @param User $user
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function sendSubscriptionAutorenewNotification(User $user): void
    {
        $this->deviceService->sendPushNotificationByUser(
            $user,
            'Your Captum subscription will be autorenewed in 3 days',
            'Your subscription will be automatically renewed in 3 days. Make sure you have enough funds on your current payment method',
            ['pushType' => Device::PUSH_TYPE_PAYMENT]
        );
    }

    /**
     * @param User $user
     * @param string|null $title
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function sendExperienceWentActive(User $user, ?string $title = null): void
    {
        $this->deviceService->sendPushNotificationByUser(
            $user,
            'Your experience is active',
            'Your experience '.$title.' is now ready for promotions!',
            ['pushType' => Device::PUSH_TYPE_EXPERIENCE]
        );
    }

    /**
     * @param User $user
     * @param string|null $title
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function sendExperienceWentRejected(User $user, ?string $title = null): void
    {
        $this->deviceService->sendPushNotificationByUser(
            $user,
            'Experience processing failed',
            'Something went wrong with '.$title.' experience. Please try to submit a new one!',
            ['pushType' => Device::PUSH_TYPE_EXPERIENCE]
        );
    }

    /**
     * @param User $user
     * @param string|null $title
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function sendExperienceWentDisabled(User $user, ?string $title = null): void
    {
        $this->deviceService->sendPushNotificationByUser(
            $user,
            'Your experience is disabled',
            'Your experience '.$title.' is deactivated. You have reached limit of available experiences!',
            ['pushType' => Device::PUSH_TYPE_EXPERIENCE]
        );
    }

    /**
     * @param User $user
     * @param string|null $title
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function sendExperienceVideoUpdateFinished(User $user, ?string $title = null): void
    {
        $this->deviceService->sendPushNotificationByUser(
            $user,
            'Your experience is updated',
            'The video of '.$title.' experience was successfully updated!',
            ['pushType' => Device::PUSH_TYPE_EXPERIENCE]
        );
    }

    /**
     * @param User $user
     * @param string|null $title
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function sendSubscriptionDeferredUpdate(User $user, ?string $title = null): void
    {
        $this->deviceService->sendPushNotificationByUser(
            $user,
            'Your subscription is updated',
            'Your subscription plan updated to: '.$title,
            ['pushType' => Device::PUSH_TYPE_PAYMENT]
        );
    }
}
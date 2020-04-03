<?php

namespace TestLogicBundle\Services;

use DateTime;
use DateTimeZone;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;
use ExperienceBundle\Entity\AbuseReport;
use ExperienceBundle\Entity\Experience;
use ExperienceBundle\Entity\TargetView;
use ExperienceBundle\Repository\ExperienceRepository;
use ExperienceBundle\Repository\TargetViewRepository;
use ExperienceBundle\Services\ExperienceService;
use JMS\JobQueueBundle\Entity\Job;
use JMS\JobQueueBundle\Entity\Repository\JobManager;
use Mixpanel;
use Money\Currency;
use Money\Money;
use SubscriptionBundle\Command\AutorenewSubscriptionChargeNotificationCommand;
use SubscriptionBundle\Command\CancelSubscriptionCommand;
use SubscriptionBundle\Entity\Subscription;
use SubscriptionBundle\Entity\Transaction;
use SubscriptionBundle\Services\BraintreeService;
use SubscriptionBundle\Services\SubscriptionService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UserApiBundle\Entity\ApiToken;
use UserApiBundle\Entity\Balance;
use UserApiBundle\Entity\Charge;
use UserApiBundle\Entity\Device;
use UserApiBundle\Entity\User;
use UserApiBundle\Entity\VerificationCode;
use UserApiBundle\Repository\UserRepository;
use UserApiBundle\Repository\VerificationCodeRepository;
use UserApiBundle\Services\BalanceService;
use UserApiBundle\Services\DeviceService;
use UserApiBundle\Services\NotificationService;
use VuforiaBundle\Services\VuforiaService;

/**
 * Class TestLogicService
 * @package TestBundle\Services
 */
class TestLogicService
{
    /** @var EntityManager */
    private $em;

    /** @var ContainerInterface $container */
    private $container;

    /** @var ExperienceRepository $experienceRepository */
    private $experienceRepository;

    /** @var SubscriptionService $subscriptionService */
    private $subscriptionService;

    /** @var TargetViewRepository $targetViewRepository */
    private $targetViewRepository;

    /** @var JobManager $jobManager */
    private $jobManager;

    /** @var BalanceService */
    private $balanceService;

    /** @var NotificationService $notificationService */
    private $notificationService;

    /** @var UserRepository $userRepository */
    private $userRepository;

    /** @var ExperienceService $experienceService */
    private $experienceService;

    /** @var BraintreeService $braintreeService */
    private $braintreeService;

    /** @var VerificationCodeRepository verificationRepository */
    private $verificationRepository;

    /** @var VuforiaService $vuforiaService */
    private $vuforiaService;

    /** @var DeviceService $deviceService */
    private $deviceService;

    /**
     * TestLogicService constructor.
     * @param ContainerInterface $container
     * @param EntityManagerInterface $em
     * @param SubscriptionService $subscriptionService
     * @param JobManager $jobManager
     * @param BalanceService $balanceService
     * @param NotificationService $notificationService
     * @param ExperienceService $experienceService
     * @param BraintreeService $braintreeService
     * @param VuforiaService $vuforiaService
     * @param DeviceService $deviceService
     */
    public function __construct(
        ContainerInterface $container,
        EntityManagerInterface $em,
        SubscriptionService $subscriptionService,
        JobManager $jobManager,
        BalanceService $balanceService,
        NotificationService $notificationService,
        ExperienceService $experienceService,
        BraintreeService $braintreeService,
        VuforiaService $vuforiaService,
        DeviceService $deviceService
    )
    {
        $this->container = $container;
        $this->em = $em;
        $this->experienceRepository = $this->em->getRepository('ExperienceBundle:Experience');
        $this->subscriptionService = $subscriptionService;
        $this->targetViewRepository = $this->em->getRepository('ExperienceBundle:TargetView');
        $this->jobManager = $jobManager;
        $this->balanceService = $balanceService;
        $this->notificationService = $notificationService;
        $this->userRepository = $this->em->getRepository(User::class);
        $this->experienceService = $experienceService;
        $this->braintreeService = $braintreeService;
        $this->verificationRepository = $this->em->getRepository(VerificationCode::class);
        $this->vuforiaService = $vuforiaService;
        $this->deviceService = $deviceService;
    }

    /**
     * @param int $id
     * @param int $totalRecognitions
     * @return Experience
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Money\UnknownCurrencyException
     * @throws \Exception
     */
    public function getExperience(int $id, int $totalRecognitions): Experience
    {
        $expr = Criteria::expr();
        $criteria = Criteria::create()
            ->setMaxResults(1)
            ->where(
                $expr->andX(
                    $expr->eq('id', $id),
                    $expr->notIn('status', [Experience::EXPERIENCE_DISABLED, Experience::EXPERIENCE_DELETED])
                )
            );

        /** @var Experience $entity */
        $experience = $this->experienceRepository->matching($criteria)->first();

        if (is_null($experience) || !$experience) {
            throw new NotFoundHttpException();
        }

        /** @var User $user */
        $user = $experience->getUser();

        if (!$user->isExample()) {
            exit;
        }

        /** @var Subscription $subscription */
        $subscription = $user->getLastSubscription();

        $previousViewsCount = $this->targetViewRepository->getTargetViewsExceptDate($experience, $subscription->getPackage()->isTrial());
        $recognitions = is_null($previousViewsCount) ? $totalRecognitions : $totalRecognitions - $previousViewsCount;

        $trialViews = 0;
        if (!$subscription->getPackage()->isTrial()) {
            $trialViews = $this->targetViewRepository->getTargetViewsTrial($experience);
        }

        $recognitions = is_null($trialViews) ? $recognitions : $recognitions - (int)$trialViews ;

        if ($recognitions > 0) {
            $freeLeftPrevious = $user->getViewsLeftFreeNumber($subscription);

            /** @var TargetView $recoEntity */
            $recoEntity = $this->targetViewRepository->getTargetViewsByDate(
                $experience,
                new DateTime('now', new DateTimeZone('UTC')),
                $subscription->getPackage()->isTrial()
            );

            $todayPreviousViews = 0;

            if (is_null($recoEntity)) {
                $recoEntity = new TargetView();
                $recoEntity->setExperience($experience);
                $recoEntity->setUser($user);
                $recoEntity->setIsTrial($subscription->getPackage()->isTrial());
            } else {
                $todayPreviousViews = $recoEntity->getViews();

                // if $todayPreviousViews & $recognitions are equal, it means no new views
                if ($todayPreviousViews === $recognitions) {
                    return $experience;
                }
            }

            $recoEntity->setViews($recognitions);

            $this->em->persist($recoEntity);
            $user->addTargetView($recoEntity);
            $this->em->flush();

            /** @var Balance $balance */
            $balance = $user->getBalance();

            /** @var int $freeLeft */
            $freeLeft = $user->getViewsLeftFreeNumber($subscription);
            $paidLeft = $user->getViewsLeftPaidNumber($subscription);

            // PUSH TYPE: limit percent reached
            if (($paidLeft >= 0 && ($freeLeft > 0 || $freeLeft <= 0))) {
                $totalFree = $user->getTotalFreeAvailableViews($subscription);
                // Percent to notification about limit percent reached
                $percentToNotify = $totalFree / 100 * Device::PUSH_VALUE_LIMIT_PERCENT_REACHED;
                $isOverPercentToNotify = $freeLeftPrevious >= $percentToNotify;
                // When the percentage for notification was exceeded after the current written off of the balance.
                $isLessPercentToNotify = $freeLeft < $percentToNotify;
                if ($isOverPercentToNotify && $isLessPercentToNotify) {
                    $this->notificationService->sendFreeViewsPercentReached($user, $balance);
                }

                if ($isLessPercentToNotify && $freeLeft <= 0 && $freeLeftPrevious >= 0) {
                    $this->notificationService->sendFreeViewsReached($user, $balance);
                }
            }

            if ($freeLeft < 0) {

                if ($paidLeft <= 0) {
                    $this->subscriptionService->disableExperiences($user);
                    if ($subscription->getPackage()->isTrial()) {
                        return $experience;
                    }
                }

                $totalPaid = $user->getTotalAvailableViews($subscription);
                $paidRecognitions = $paidLeft === $totalPaid ? abs($freeLeft) : $recognitions - $todayPreviousViews;

                /** @var int $chargeAmount amount on cents for new recognitions for balance charge */
                $chargeAmount = (int)$paidRecognitions * Balance::PAY_PER_RECOGNITION;
                $startBalance = $balance->getAmount()->getAmount();
                $balanceAmount = $startBalance - abs($chargeAmount);

                if ($balanceAmount <= 0) {
                    $this->subscriptionService->disableExperiences($user);
                }

                $charge = new Charge();
                $charge->setExperience($experience);
                $charge->setBalance($balance);
                $charge->setAmount(new Money((int)$chargeAmount, new Currency('USD')));
                $this->em->persist($charge);

                $balance->setAmount(new Money((int)$balanceAmount, new Currency('USD')));
                $balance->addCharge($charge);
                $this->em->persist($balance);

                $this->em->flush();

                /** @var int $paidLeftAfterCharge */
                $paidLeftAfterCharge = $user->getViewsLeftPaidNumber($subscription);

                if ($paidLeftAfterCharge <= 0) {
                    $this->subscriptionService->disableExperiences($user);
                    $this->notificationService->sendLimitOrBalanceReached($user, $balance);
                } else {
                    // PUSH TYPE: limit percent reached
                    if ($balance->isChargeLimitEnabled() && $balance->isLimitWarningEnabled()) {
                        $totalPaid = $user->getTotalAvailableViews($subscription);
                        // Percent to notification about limit percent reached
                        $percentToNotify = $totalPaid / 100 * Device::PUSH_VALUE_LIMIT_PERCENT_REACHED;
                        $isOverPercentToNotify = $paidLeft >= $percentToNotify;
                        // When the percentage for notification was exceeded after the current written off of the balance.
                        $isLessPercentToNotify = $paidLeftAfterCharge < $percentToNotify;
                        if ($isOverPercentToNotify && $totalPaid > 0 && $isLessPercentToNotify) {
                            $this->notificationService->sendLimitPercentReached($user);
                        }
                    }
                    // PUSH TYPE: balance percent reached
                    $initBalanceAmount = $this->balanceService->getInitBalanceSinceLastRefillAmount($user);
                    // Value to notification about balance percent reached
                    $valueToNotify = $initBalanceAmount / 100 * Device::PUSH_VALUE_BALANCE_PERCENT_REACHED;
                    // Is balance is above the value before the balance is written off to prevent re-sending the notification
                    $isOverPercentToNotify = $startBalance > $valueToNotify;
                    // When the percentage for notification was exceeded after the current written off of the balance.
                    $isLessPercentToNotify = $balanceAmount <= $valueToNotify;
                    if ($isOverPercentToNotify && $isLessPercentToNotify) {
                        $this->notificationService->sendBalancePercentReached($user);
                    }
                }
            }
        }

        return $experience;
    }

    /**
     * @param Subscription $subscription
     * @param string $kind
     * @param int|null $currentBillingCycle
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Money\UnknownCurrencyException
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function testAutorenew(Subscription $subscription, string $kind, ?int $currentBillingCycle = null): void
    {
        /** @var User $user */
        $user = $subscription->getUser();

        if (!$user->isExample()) {
            return;
        }

        switch ($kind) {
            case 'subscription_went_past_due':
                $subscription->setStatus(Subscription::STATUS_PAST_DUE);
                $this->subscriptionService->disableExperiences($user);
                $this->notificationService->sendSubscriptionAutorenewFailed($user);

                break;
            case 'subscription_expired':
                $subscription->setStatus(Subscription::STATUS_EXPIRED);
                $this->subscriptionService->disableExperiences($user);
                $this->notificationService->sendSubscriptionExpired($user);

                break;
            case 'subscription_charged_unsuccessfully':
                $subscription->setStatus(Subscription::STATUS_CHARGED_UNSUCCESSFULLY);
                $this->subscriptionService->disableExperiences($user);
                $this->notificationService->sendSubscriptionAutorenewChargeFailed($user);

                break;
            case 'subscription_went_active':
                /** @var Balance|null $balance */
                $balance = $user->getBalance();

                $subscription->setStatus(Subscription::STATUS_ACTIVE);
                $subscription->setExpiresAt((new \DateTime())
                    ->add(new \DateInterval('P'.$subscription->getPackage()->getExpiresInMonths().'M')));
                $subscription->setInitialBalanceAmount(is_null($balance) ? 0 : $balance->getAmount()->getAmount());
                $this->em->persist($subscription);

                $freeLeft = $user->getViewsLeftFreeNumber($subscription);
                $paidLeft = $user->getViewsLeftPaidNumber($subscription);

                if ($freeLeft > 0 || $paidLeft > 0) {
                    $this->subscriptionService->restoreExperiences($user, $subscription);
                }

                /** @var Job $jobAutorenewChargeNotification */
                $jobAutorenewChargeNotification = $this->jobManager->findOpenJobForRelatedEntity(AutorenewSubscriptionChargeNotificationCommand::NAME, $subscription);

                if (is_null($jobAutorenewChargeNotification)) {
                    $jobAutorenewChargeNotification = new Job(AutorenewSubscriptionChargeNotificationCommand::NAME, [
                        sprintf('--%s=%d', AutorenewSubscriptionChargeNotificationCommand::SUBSCRIPTION_OBJECT_ID, $subscription->getId()),
                    ]);

                    $jobAutorenewChargeNotification->addRelatedEntity($subscription);
                    $jobAutorenewChargeNotification->setExecuteAfter(
                        $subscription->getExpiresAt()->sub(new \DateInterval('P3D'))
                    );

                    $this->em->persist($jobAutorenewChargeNotification);
                }

                break;
            case 'subscription_charged_successfully':
                /** @var Balance|null $balance */
                $balance = $user->getBalance();

                $subscription->setStatus(Subscription::STATUS_ACTIVE);
                $subscription->setExpiresAt((new \DateTime())
                    ->add(new \DateInterval('P'.$subscription->getPackage()->getExpiresInMonths().'M')));
                $subscription->setInitialBalanceAmount(is_null($balance) ? 0 : $balance->getAmount()->getAmount());
                $this->em->persist($subscription);

                $freeLeft = $user->getViewsLeftFreeNumber($subscription);
                $paidLeft = $user->getViewsLeftPaidNumber($subscription);

                if ($freeLeft > 0 || $paidLeft > 0) {
                    $this->subscriptionService->restoreExperiences($user, $subscription);
                }

                if (!is_null($currentBillingCycle) && $currentBillingCycle > 1) {
                    $this->notificationService->sendSubscriptionAutorenew($user);
                }

                /** @var Job $jobAutorenewChargeNotification */
                $jobAutorenewChargeNotification = $this->jobManager->findOpenJobForRelatedEntity(AutorenewSubscriptionChargeNotificationCommand::NAME, $subscription);

                if (is_null($jobAutorenewChargeNotification)) {
                    $jobAutorenewChargeNotification = new Job(AutorenewSubscriptionChargeNotificationCommand::NAME, [
                        sprintf('--%s=%d', AutorenewSubscriptionChargeNotificationCommand::SUBSCRIPTION_OBJECT_ID, $subscription->getId()),
                    ]);

                    $jobAutorenewChargeNotification->addRelatedEntity($subscription);
                    $jobAutorenewChargeNotification->setExecuteAfter(
                        $subscription->getExpiresAt()->sub(new \DateInterval('P3D'))
                    );

                    $this->em->persist($jobAutorenewChargeNotification);
                }
                break;
            case 'subscription_canceled':
                $subscription->setIsAutorenew(false);
                $jobCancelSubscription = new Job(CancelSubscriptionCommand::NAME, [
                    sprintf('--%s=%d', CancelSubscriptionCommand::SUBSCRIPTION_OBJECT_ID, $subscription->getId()),
                ]);

                $jobCancelSubscription->setExecuteAfter($subscription->getExpiresAt());
                $this->em->persist($jobCancelSubscription);
                break;
        }

        $this->em->persist($subscription);
        $this->em->flush();
    }

    /**
     * @param User $user
     * @return bool
     * @throws ORMException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function deleteUser(User $user): bool
    {
        if ($user->getCustomerId()) {
            try {
                $this->braintreeService->deleteCustomer($user->getCustomerId());
            } catch (\Exception $exception) {}
        }

        /** @var VerificationCode[] $verificationCodes */
        $verificationCodes = $this->verificationRepository->findBy(['email' => $user->getEmail()]);
        foreach ($verificationCodes as $verificationCode) {
            $this->em->remove($verificationCode);
        }

        /** @var Transaction[] $transactions */
        $transactions = $user->getTransactions();
        foreach ($transactions as $transaction) {
            $this->em->remove($transaction);
        }

        /** @var Subscription[] $subscriptions */
        $subscriptions = $user->getSubscription();
        foreach ($subscriptions as $subscription) {
            $this->em->remove($subscription);
        }

        /** @var Balance $balance */
        $balance = $user->getBalance();

        if (!is_null($balance)) {

            /** @var Charge[] $charges */
            $charges = $balance->getCharges();
            foreach ($charges as $charge) {
                $this->em->remove($charge);
            }

            $this->em->remove($balance);
        }

        /** @var Device[] $devices */
        $devices = $user->getDevices();
        foreach ($devices as $device) {
            try {
                $this->deviceService->deleteByToken($user, $device->getDeviceToken());
            } catch (\Exception $exception) {
                continue;
            }
        }

        /** @var ApiToken[] $apiTokens */
        $apiTokens = $user->getApiTokens();
        foreach ($apiTokens as $apiToken) {
            $this->em->remove($apiToken);
        }

        /** @var TargetView[] $targetViews */
        $targetViews = $user->getTargetView();
        foreach ($targetViews as $targetView) {
            $this->em->remove($targetView);
        }

        /** @var Experience[] $experiences */
        $experiences = $user->getExperience();
        foreach ($experiences as $experience) {

            /** @var AbuseReport $abuseReports */
            $abuseReports = $experience->getReports();
            foreach ($abuseReports as $abuseReport) {
                $this->em->remove($abuseReport);
            }

            if ($experience->getTargetId()) {
                $this->vuforiaService->deleteTarget($experience->getTargetId());
            }

            $this->experienceService->deleteFromBucket($experience);
            $this->em->remove($experience);
        }

        if ($this->container->hasParameter('mixpanel_token')) {
            $mp = Mixpanel::getInstance($this->container->getParameter('mixpanel_token'));
            $mp->people->deleteUser($user->getId());
        }

        $this->em->remove($user);
        $this->em->flush();

        return true;
    }

    /**
     * @param string $email
     * @return User|null
     */
    public function getUserByEmail(string $email): ?User
    {
        return $this->userRepository->findOneBy(['email' => $email]);
    }
}
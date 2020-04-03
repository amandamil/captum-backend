<?php

namespace SubscriptionBundle\Services;

use Braintree\WebhookNotification;
use CoreBundle\Exception\FormValidationException;
use CoreBundle\Services\ResponseService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use ExperienceBundle\Entity\Experience;
use ExperienceBundle\Repository\ExperienceRepository;
use JMS\JobQueueBundle\Entity\Job;
use JMS\JobQueueBundle\Entity\Repository\JobManager;
use Money\Currency;
use Money\Money;
use Monolog\Logger;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use ReceiptValidator\iTunes\AbstractResponse;
use ReceiptValidator\iTunes\PendingRenewalInfo;
use ReceiptValidator\iTunes\PurchaseItem;
use ReceiptValidator\iTunes\ResponseInterface;
use SubscriptionBundle\Command\AppleInAppCommands\CheckAutorenewSubscriptionCommand;
use SubscriptionBundle\Entity\Product;
use SubscriptionBundle\Repository\ProductRepository;
use SubscriptionBundle\Repository\TransactionRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use SubscriptionBundle\Command\CancelSubscriptionCommand;
use SubscriptionBundle\Entity\Package;
use SubscriptionBundle\Entity\Subscription;
use SubscriptionBundle\Entity\Transaction;
use SubscriptionBundle\Form\SubscriptionType;
use SubscriptionBundle\Form\SubscriptionUpdateType;
use SubscriptionBundle\Model\SubscriptionProcessingResult;
use SubscriptionBundle\Repository\PackageRepository;
use SubscriptionBundle\Repository\SubscriptionRepository;
use SubscriptionBundle\Command\AutorenewSubscriptionChargeNotificationCommand;
use Swift_Mailer;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use UserApiBundle\Entity\ApiToken;
use UserApiBundle\Entity\Balance;
use UserApiBundle\Entity\User;
use UserApiBundle\Services\AwsSesService;
use UserApiBundle\Services\NotificationService;
use VuforiaBundle\Services\VuforiaService;

/**
 * Class SubscriptionService
 * @package SubscriptionBundle\Services
 */
class SubscriptionService
{
    /** @var EntityManager */
    private $em;

    /** @var ContainerInterface $container */
    private $container;

    /** @var BraintreeService $braintreeService */
    private $braintreeService;

    /** @var FormFactoryInterface $formFactory */
    private $formFactory;

    /** @var ResponseService $responseService */
    private $responseService;

    /** @var PackageRepository $packagesRepository */
    private $packagesRepository;

    /** @var SubscriptionRepository $subscriptionRepository */
    private $subscriptionRepository;

    /** @var VuforiaService $vuforiaService */
    private $vuforiaService;

    /** @var JobManager $jobManager */
    private $jobManager;

    /** @var AwsSesService $sesService */
    private $sesService;

    /** @var ExperienceRepository $experienceRepository */
    private $experienceRepository;

    /** @var Swift_Mailer $mailer */
    private $mailer;

    /** @var \Twig_Environment $templating */
    private $templating;

    /** @var NotificationService $notificationService */
    private $notificationService;

    /** @var AppstoreService $appstoreService */
    private $appstoreService;

    /** @var Logger $logger */
    private $logger;

    /** @var ProductRepository $productRepository */
    private $productRepository;

    /** @var TransactionRepository $transactionRepository */
    private $transactionRepository;

    /**
     * SubscriptionService constructor.
     * @param EntityManagerInterface $em
     * @param ContainerInterface     $container
     * @param BraintreeService       $braintreeService
     * @param FormFactoryInterface   $formFactory
     * @param ResponseService        $responseService
     * @param VuforiaService         $vuforiaService
     * @param JobManager             $jobManager
     * @param AwsSesService          $sesService
     * @param Swift_Mailer           $mailer
     * @param NotificationService    $notificationService
     * @param AppstoreService        $appstoreService
     * @param Logger                 $logger
     */
    public function __construct(
        EntityManagerInterface $em,
        ContainerInterface $container,
        BraintreeService $braintreeService,
        FormFactoryInterface $formFactory,
        ResponseService $responseService,
        VuforiaService $vuforiaService,
        JobManager $jobManager,
        AwsSesService $sesService,
        Swift_Mailer $mailer,
        NotificationService $notificationService,
        AppstoreService $appstoreService,
        Logger $logger
    )
    {
        $this->em = $em;
        $this->container = $container;
        $this->braintreeService = $braintreeService;
        $this->formFactory = $formFactory;
        $this->responseService = $responseService;
        $this->packagesRepository = $this->em->getRepository('SubscriptionBundle:Package');
        $this->subscriptionRepository = $this->em->getRepository('SubscriptionBundle:Subscription');
        $this->jobManager = $jobManager;
        $this->vuforiaService = $vuforiaService;
        $this->sesService = $sesService;
        $this->experienceRepository = $this->em->getRepository('ExperienceBundle:Experience');
        $this->mailer = $mailer;
        $this->templating = $this->container->get('twig');
        $this->notificationService = $notificationService;
        $this->appstoreService = $appstoreService;
        $this->logger = $logger;
        $this->productRepository = $this->em->getRepository(Product::class);
        $this->transactionRepository = $this->em->getRepository(Transaction::class);
    }

    /**
     * @param User $user
     * @return string
     */
    public function createToken(User $user): string
    {
        if (is_null($user->getCustomerId())) {
            throw new BadRequestHttpException('You need verify your account!');
        }

        return $this->braintreeService->generateClientToken($user->getCustomerId());
    }

    /**
     * @param Request $request
     * @param User $user
     * @return Subscription
     * @throws \Exception
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function assignSubscription(Request $request, User $user): Subscription
    {
        /** @var Subscription|false $lastSubscription */
        $lastSubscription = $user->getLastSubscription();

        $entity = $lastSubscription && $lastSubscription->getStatus() === Subscription::STATUS_PENDING ? $lastSubscription : new Subscription();

        $form = $this->formFactory->create(SubscriptionType::class, $entity);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            throw new BadRequestHttpException('Wrong Request');
        }

        if (!$form->isValid()) {
            throw new FormValidationException($this->responseService->getFormError($form));
        }

        $scope = $request->headers->get('scope');

        /** @var SubscriptionProcessingResult $validationResult */
        $validationResult = $this->validatePackage($request, $user, $entity, $scope);

        if (!$validationResult->isSuccess()) {
            throw new BadRequestHttpException($validationResult->getMessage());
        }

        /** @var Balance $balance */
        $balance = $user->getBalance();

        if (is_null($balance)) {
            $money = new Money(0, new Currency('USD'));

            $balance = new Balance();
            $balance->setAmount($money);
            $balance->setUser($user);

            $this->em->persist($balance);
            $this->em->flush();
        }

        $entity->setUser($user);
        $entity->setInitialBalanceAmount($balance->getAmount()->getAmount());

        switch ($entity->getPackage()->isTrial()) {
            case true:
                $user->setIsTrialUsed(true);
                $entity->setStatus(Subscription::STATUS_ACTIVE);
                $entity->setExpiresAt((new \DateTime())->add(new \DateInterval('P2M')));

                $this->em->persist($entity);
                $this->em->flush();

                $jobCancelSubscription = new Job(CancelSubscriptionCommand::NAME, [
                    sprintf('--%s=%d', CancelSubscriptionCommand::SUBSCRIPTION_OBJECT_ID, $entity->getId()),
                ]);

                $jobCancelSubscription->setExecuteAfter($entity->getExpiresAt());
                $jobCancelSubscription->addRelatedEntity($entity);

                $this->em->persist($jobCancelSubscription);
                break;
            case false:
                $nonce = $request->request->get('payment_method_nonce');

                switch ($scope) {
                    case null:
                    case ApiToken::SCOPE_ANDROID:
                        $this->processBraintreeAssignSubscription($entity, $user, $nonce);
                        break;
                    case ApiToken::SCOPE_IOS:
                        /** @var Subscription|null $braintreeSubscription */
                        $braintreeSubscription = $this->subscriptionRepository->findOneBy([
                            'user' => $user,
                            'providerType' => Subscription::PROVIDER_BRAINTREE,
                        ]);

                        if (!is_null($braintreeSubscription)) {
                            $result = $this->braintreeService->deleteSubscription($braintreeSubscription->getBraintreeId());
                            if ($result->success) {
                                $this->disableSubscription($braintreeSubscription->getId());
                            }
                        }

                        $entity->setStatus(Subscription::STATUS_PENDING);
                        $entity->setExpiresAt((new \DateTime())->add(new \DateInterval('P1D')));
                        $entity->setProviderType(Subscription::PROVIDER_APPLE_IN_APP);

                        $this->restoreExperiences($user, $entity);

                        break;
                }

                $this->em->persist($entity);
                $this->em->flush();

                $jobAutorenewChargeNotification = new Job(AutorenewSubscriptionChargeNotificationCommand::NAME, [
                    sprintf('--%s=%d', AutorenewSubscriptionChargeNotificationCommand::SUBSCRIPTION_OBJECT_ID, $entity->getId()),
                ]);

                /** @var \DateTime $executeAt */
                $executeAt = (clone $entity->getExpiresAt())->sub(new \DateInterval('P3D'));

                $jobAutorenewChargeNotification->addRelatedEntity($entity);
                $jobAutorenewChargeNotification->setExecuteAfter($executeAt);

                $this->em->persist($jobAutorenewChargeNotification);
                break;
        }

        $this->em->flush();

        return $entity;
    }

    /**
     * @param User $user
     * @param int  $page
     * @param int  $perPage
     * @return array
     * @throws \Exception
     */
    public function listPackages(User $user, int $page, int $perPage): array
    {
        /** @var Subscription $current */
        $current = $user->getActiveSubscription();
        $packageId = $current && $current->isAutorenew() ? $current->getPackage()->getId() : null;
        $query = $this->packagesRepository->getAvailablePackages($user, $packageId);

        $adapter = new DoctrineORMAdapter($query);
        $paginator = new Pagerfanta($adapter);

        $paginator->setAllowOutOfRangePages(true);

        $paginator->setMaxPerPage($perPage);
        $paginator->setCurrentPage($page);

        $pagerArray = [
            "count" => $paginator->getNbResults(),
            "pageCount" => $paginator->getNbPages(),
            "packages" => (array)$paginator->getCurrentPageResults()
        ];

        return $pagerArray;
    }

    /**
     * @param Request $request
     * @param Subscription $currentSubscription
     * @param User $user
     * @return Subscription
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Money\UnknownCurrencyException
     */
    public function changeSubscription(Request $request, Subscription $currentSubscription, User $user): Subscription
    {
        /** @var string|null $scope */
        $scope = $request->headers->get('scope');

        /** @var Package $previousPackage */
        $previousPackage = $currentSubscription->getPackage();

        $form = $this->formFactory->create(
            $scope === ApiToken::SCOPE_IOS ? SubscriptionType::class : SubscriptionUpdateType::class,
            $currentSubscription
        );

        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            throw new BadRequestHttpException('Wrong Request');
        }

        if (!$form->isValid()) {
            throw new FormValidationException($this->responseService->getFormError($form));
        }

        if ($previousPackage->isTrial()) {
            throw new BadRequestHttpException('Something went wrong. Please try again later');
        }

        if (!$currentSubscription->getPackage()->isPublic()) {
            throw new BadRequestHttpException('You can use only public plans!');
        }

        if ($currentSubscription->getPackage()->isTrial()) {
            throw new BadRequestHttpException('You have already used Trial plan. Subscribe to a paid one to continue your promotions.');
        }

        if ($currentSubscription->isAutorenew() && $previousPackage->getId() === $currentSubscription->getPackage()->getId()) {
            throw new BadRequestHttpException('This plan is already in use');
        }

        if (($currentSubscription->getProviderType() === Subscription::PROVIDER_BRAINTREE && $scope === ApiToken::SCOPE_IOS) ||
            ($currentSubscription->getProviderType() === Subscription::PROVIDER_APPLE_IN_APP && $scope === ApiToken::SCOPE_ANDROID))
        {
            throw new BadRequestHttpException('First you need to cancel the previous subscription.');
        }

        $previousChangeAt = is_null($currentSubscription->getChangedPlanAt())
            ? $currentSubscription->getCreatedAt()
            : $currentSubscription->getChangedPlanAt();

        $diff = $previousChangeAt->diff(new \DateTime());

        $minutes = ((int)$diff->format('%a') * 1440) +
            ((int)$diff->format('%h') * 60) +
            (int)$diff->format('%i');

        if ($scope === ApiToken::SCOPE_ANDROID && $minutes <= 30) {
            throw new BadRequestHttpException('You must wait at least 30 minutes between plan changes');
        }

        $nonce = $request->request->get('payment_method_nonce');

        switch ($scope) {
            case null:
            case ApiToken::SCOPE_ANDROID:
                $this->processBraintreeChangeSubscription($currentSubscription, $user, $previousPackage, $nonce);
                $this->disableExperiencesAfterDowngrade($currentSubscription, $previousPackage, $user);
                break;
            case ApiToken::SCOPE_IOS:
                $currentSubscription
                    ->setAppleDowngradeEnabled($previousPackage->getExperiencesNumber() > $currentSubscription->getPackage()->getExperiencesNumber());
                $currentSubscription->setNextPlan($currentSubscription->getPackage());
                $currentSubscription->setPackage($previousPackage);
                break;
        }

        $this->em->persist($currentSubscription);
        $this->em->flush();

        return $currentSubscription;
    }

    /**
     * @param Request $request
     * @return bool
     * @throws \Exception
     * @throws \Braintree\Exception\InvalidSignature
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function processNotification(Request $request): bool
    {
        /** @var WebhookNotification $notificationValue */
        $notificationValue = $this->braintreeService->parseWebhookNotification($request->request->all());

        if ($notificationValue->kind === 'check') {
            return true;
        }

        /** @var Subscription $subscription */
        $subscription = $this->subscriptionRepository->findOneBy([
            'braintreeId' => $notificationValue->subscription->id
        ]);

        if (is_null($subscription)) {
            return false;
        }

        switch ($notificationValue->kind) {
            /**
             * A subscription has moved from the Active status to the Past Due status.
             * This will only be triggered when the initial transaction in a billing cycle is declined.
             * Once the status moves to past due, it will not be triggered again in that billing cycle.
             */
            case 'subscription_went_past_due':
                /** @var User $user */
                $user = $subscription->getUser();

                $subscription->setStatus(Subscription::STATUS_PAST_DUE);
                $this->disableExperiences($user);

                $this->notificationService->sendSubscriptionAutorenewFailed($user);

                break;
            /**
             * A subscription reaches the specified number of billing cycles and expires.
             */
            case 'subscription_expired':
                /** @var User $user */
                $user = $subscription->getUser();

                $subscription->setStatus(Subscription::STATUS_EXPIRED);
                $this->disableExperiences($user);

                $this->notificationService->sendSubscriptionExpired($user);

                break;
            /**
             * A subscription already exists and fails to create a successful charge. 
             * This will not trigger on manual retries or if the attempt to create a subscription fails due to an unsuccessful transaction.
             */
            case 'subscription_charged_unsuccessfully':
                /** @var User $user */
                $user = $subscription->getUser();

                $subscription->setStatus(Subscription::STATUS_CHARGED_UNSUCCESSFULLY);
                $this->disableExperiences($user);

                $this->notificationService->sendSubscriptionAutorenewChargeFailed($user);

                break;
            /**
             * A subscription's first authorized transaction is created, 
             * or a successful transaction moves a subscription from the Past Due status to the Active status. 
             */
            case 'subscription_went_active':
                /** @var User $user */
                $user = $subscription->getUser();

                /** @var Balance|null $balance */
                $balance = $user->getBalance();

                $subscription->setStatus(Subscription::STATUS_ACTIVE);
                $subscription->setExpiresAt($notificationValue->subscription->billingPeriodEndDate);
                $subscription->setInitialBalanceAmount(is_null($balance) ? 0 : $balance->getAmount()->getAmount());
                $this->em->persist($subscription);

                $freeLeft = $user->getViewsLeftFreeNumber($subscription);
                $paidLeft = $user->getViewsLeftPaidNumber($subscription);

                if ($freeLeft > 0 || $paidLeft > 0) {
                    $this->restoreExperiences($user, $subscription);
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
            /**
             * A subscription successfully moves to the next billing cycle. 
             * This will also occur when either a new transaction is created mid-cycle due to proration on an upgrade 
             * or a billing cycle is skipped due to the presence of a negative balance that covers the cost of the subscription.
             */
            case 'subscription_charged_successfully':
                /** @var User $user */
                $user = $subscription->getUser();

                /** @var Balance|null $balance */
                $balance = $user->getBalance();

                $subscription->setStatus(Subscription::STATUS_ACTIVE);
                $subscription->setExpiresAt($notificationValue->subscription->billingPeriodEndDate);
                $subscription->setInitialBalanceAmount(is_null($balance) ? 0 : $balance->getAmount()->getAmount());
                $this->em->persist($subscription);

                $transaction = new Transaction();
                $transaction->setTransactionId($notificationValue->transaction->id);
                $transaction->setUser($user);
                $transaction->setStatus(Transaction::STATUS_SUCCESS);
                $transaction->setType(Transaction::TYPE_SUBSCRIPTION);
                $transaction->setAmount($subscription->getPackage()->getPrice());
                $transaction->setProviderType(Subscription::PROVIDER_BRAINTREE);
                $this->em->persist($transaction);

                $freeLeft = $user->getViewsLeftFreeNumber($subscription);
                $paidLeft = $user->getViewsLeftPaidNumber($subscription);

                if ($freeLeft > 0 || $paidLeft > 0) {
                    $this->restoreExperiences($user, $subscription);
                }

                if ($notificationValue->subscription->currentBillingCycle > 1) {
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
            /**
             * A subscription is canceled.
             */
            case 'subscription_canceled':
                $subscription->setIsAutorenew(false);
                $jobCancelSubscription = new Job(CancelSubscriptionCommand::NAME, [
                    sprintf('--%s=%d', CancelSubscriptionCommand::SUBSCRIPTION_OBJECT_ID, $subscription->getId()),
                ]);

                $jobCancelSubscription->setExecuteAfter($subscription->getExpiresAt());
                $this->em->persist($jobCancelSubscription);

                /** @var Job $jobAutorenewChargeNotification */
                $jobAutorenewChargeNotification = $this->jobManager->findOpenJobForRelatedEntity(AutorenewSubscriptionChargeNotificationCommand::NAME, $subscription);

                if (!is_null($jobAutorenewChargeNotification)) {
                    $jobAutorenewChargeNotification->setState(Job::STATE_CANCELED);
                    $this->em->persist($jobAutorenewChargeNotification);
                }

                break;
        }

        $this->em->persist($subscription);
        $this->em->flush();

        return true;
    }

    /**
     * @param Subscription $currentSubscription
     * @return Subscription
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function cancelSubscription(Subscription $currentSubscription): Subscription
    {
        $result = $this->braintreeService->cancelSubscription($currentSubscription->getBraintreeId());

        if ($result->success) {
            $currentSubscription->setIsAutorenew(false);

            /** @var Job $jobAutorenewChargeNotification */
            $jobAutorenewChargeNotification = $this->jobManager->findOpenJobForRelatedEntity(AutorenewSubscriptionChargeNotificationCommand::NAME, $currentSubscription);

            if (!is_null($jobAutorenewChargeNotification)) {
                $jobAutorenewChargeNotification->setState(Job::STATE_CANCELED);
                $this->em->persist($jobAutorenewChargeNotification);
            }

            $this->em->persist($currentSubscription);
            $this->em->flush();
        }

        return $currentSubscription;
    }

    /**
     * @param int $subscriptionId
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function disableSubscription(int $subscriptionId): void
    {
        /** @var Subscription $subscription */
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (is_null($subscription)) {
            return;
        }

        $subscription->setStatus(Subscription::STATUS_CANCELED);

        foreach ($subscription->getUser()->getExperience() as $experience) {
            if ($experience->getStatus() === Experience::EXPERIENCE_ACTIVE) {

                $experience->setStatus(Experience::EXPERIENCE_DISABLED);
                $this->em->persist($experience);

                $this->vuforiaService->updateTarget($experience,0);
            }
        }

        $this->em->persist($subscription);
        $this->em->flush();

        $subscription->getPackage()->isTrial()
            ? $this->notificationService->sendSubscriptionTrialExpired($subscription->getUser())
            : $this->notificationService->sendSubscriptionExpired($subscription->getUser());
    }

    /**
     * @param Subscription $subscription
     * @param string $nonceFromTheClient
     *
     * @return Subscription
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Money\UnknownCurrencyException
     */
    public function updatePaymentMethod(Subscription $subscription, string $nonceFromTheClient): Subscription
    {
        $result = $this->braintreeService->updatePaymentMethod(
            $subscription->getBraintreeId(),
            $nonceFromTheClient
        );

        if ($result->success) {
            if ((!$subscription->isAutorenew() && $subscription->getStatus() === Subscription::STATUS_PAST_DUE)
                || $subscription->getStatus() === Subscription::STATUS_CHARGED_UNSUCCESSFULLY)
            {
                $retryResult = $this->braintreeService->retryCharge($subscription);
                $transaction = $retryResult->transaction;

                $chargeTransaction = new Transaction();
                $chargeTransaction->setTransactionId($transaction->id);
                $chargeTransaction->setUser($subscription->getUser());
                $chargeTransaction->setStatus($transaction->status);
                $chargeTransaction->setType(Transaction::TYPE_SUBSCRIPTION);
                $chargeTransaction->setAmount(new Money((int)($transaction->amount*100), new Currency('USD')));

                $this->em->persist($chargeTransaction);

                $restoreResult = $this->braintreeService->restoreSubscription($subscription->getBraintreeId());

                if ($restoreResult->success) {
                    $subscription->setIsAutorenew(true);
                }

                $this->em->persist($subscription);
                $this->em->flush();
            }

            return $subscription;
        }

        return null;
    }

    /**
     * @param User $user
     * @throws \Doctrine\ORM\ORMException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function disableExperiences(User $user): void
    {
        foreach ($user->getExperience() as $experience) {
            if ($experience->getStatus() === Experience::EXPERIENCE_ACTIVE) {

                $experience->setStatus(Experience::EXPERIENCE_DISABLED);
                $experience->setIsLastUsed(true);

                $this->em->persist($experience);
                $this->em->flush();

                $this->vuforiaService->updateTarget($experience,0);
            }
        }
    }

    /**
     * @param User $user
     * @param Subscription $subscription
     * @throws \Doctrine\ORM\ORMException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function restoreExperiences(User $user, Subscription $subscription): void
    {
        $availableExperiences = $subscription->getPackage()->getExperiencesNumber();
        $experiences = $this->experienceRepository->findUserDeactivatedExperiences($user, $availableExperiences);

        /** @var Experience $experience */
        foreach ($experiences as $experience) {
            $experience->setStatus(Experience::EXPERIENCE_ACTIVE);
            $experience->setIsLastUsed(false);

            $this->em->persist($experience);

            $this->vuforiaService->updateTarget($experience,1);
        }
    }

    /**
     * @param int $subscriptionId
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function autorenewChargeNotification(int $subscriptionId): void
    {
        /** @var Subscription $subscription */
        $subscription = $this->subscriptionRepository->findOneBy([
            'id' => $subscriptionId,
            'status' => Subscription::STATUS_ACTIVE
        ]);

        if ($subscription) {
            /** @var User $user */
            $user = $subscription->getUser();
            $this->notificationService->sendSubscriptionAutorenewNotification($user);

            $this->sesService->sendEmail(
                $user->getEmail(),
                $this->templating->render('emails/subscription_autorenew_notification.html.twig'),
                'Your Captum subscription will be autorenewed in 3 days'
            );
        }
    }

    /**
     * @param Subscription $entity
     * @param User $user
     * @param string $nonce
     * @throws \Doctrine\ORM\ORMException
     * @throws \Money\UnknownCurrencyException
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function processBraintreeAssignSubscription(Subscription $entity, User $user, string $nonce): void
    {
        /** @var Subscription|bool $lastSubscription */
        $lastSubscription = $user->getLastSubscription();

        if ($lastSubscription
            && $lastSubscription->getPackage()->isTrial()
            && $lastSubscription->getStatus() !== Subscription::STATUS_CANCELED)
        {
            $this->logger->addWarning('Is active trial: '.$lastSubscription->getPackage()->isTrial());
            $this->logger->addWarning('Status: '.$lastSubscription->getStatus());

            $lastSubscription->setStatus(Subscription::STATUS_CANCELED);

            /** @var Job $cancelJob */
            $cancelJob = $this->jobManager->findOpenJobForRelatedEntity(CancelSubscriptionCommand::NAME, $lastSubscription);
            if (!is_null($cancelJob)) {
                $cancelJob->setState(Job::STATE_CANCELED);
                $this->em->persist($cancelJob);
            }

            $this->em->persist($lastSubscription);

            $this->disableExperiencesAfterDowngrade($entity, $lastSubscription->getPackage(), $user);
        }

        $result = $this->braintreeService->createSubscription($nonce, $entity->getPackage()->getBraintreePlanId());
        $subscription = $result->subscription;

        $transaction = new Transaction();
        $transaction->setTransactionId($subscription->transactions[0]->id);
        $transaction->setStatus($subscription->transactions[0]->status);
        $transaction->setUser($user);
        $transaction->setType(Transaction::TYPE_SUBSCRIPTION);
        $transaction->setAmount($entity->getPackage()->getPrice());
        $transaction->setProviderType(Subscription::PROVIDER_BRAINTREE);

        $this->em->persist($transaction);

        $entity->setStatus($subscription->status === 'Active' ? Subscription::STATUS_ACTIVE : $subscription->status);
        $entity->setBraintreeId($subscription->id);
        $entity->setProviderType(Subscription::PROVIDER_BRAINTREE);
        $entity->setExpiresAt((new \DateTime())->add(new \DateInterval('P'.$entity->getPackage()->getExpiresInMonths().'M')));
    }

    /**
     * @param Subscription $entity
     * @param User $user
     * @param Package $previousPackage
     * @param string $nonce
     * @throws \Doctrine\ORM\ORMException
     * @throws \Money\UnknownCurrencyException
     */
    private function processBraintreeChangeSubscription(Subscription $entity, User $user, Package $previousPackage, string $nonce): void
    {
        $result = $this->braintreeService->updateSubscription(
            $entity->getBraintreeId(),
            $nonce,
            $entity->getPackage()->getBraintreePlanId(),
            number_format(($entity->getPackage()->getPrice()->getAmount()/100))
        );

        $subscription = $result->subscription;

        if ($previousPackage->getPrice()->getAmount() < $entity->getPackage()->getPrice()->getAmount()) {
            $braintreeTransaction = $subscription->transactions[0];

            $transaction = new Transaction();
            $transaction->setTransactionId($braintreeTransaction->id);
            $transaction->setUser($user);
            $transaction->setStatus($braintreeTransaction->status);
            $transaction->setType(Transaction::TYPE_SUBSCRIPTION);
            $transaction->setAmount(new Money((int)($braintreeTransaction->amount*100), new Currency('USD')));
            $transaction->setProviderType(Subscription::PROVIDER_BRAINTREE);

            $this->em->persist($transaction);
        }

        $entity->setStatus($subscription->status === 'Active' ? Subscription::STATUS_ACTIVE : $subscription->status);
        $entity->setIsAutorenew(true);
        $entity->setChangedPlanAt(new \DateTime());
    }

    /**
     * @param Subscription $entity
     * @param User $user
     * @param PurchaseItem $result
     * @param string $receipt
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Money\UnknownCurrencyException
     */
    private function processInAppAssignSubscription(Subscription $entity, User $user, PurchaseItem $result, string $receipt): void
    {
        /** @var Subscription|bool $lastSubscription */
        $lastSubscription = $user->getLastSubscription();

        if ($lastSubscription
            && $lastSubscription->getPackage()->isTrial()
            && $lastSubscription->getStatus() !== Subscription::STATUS_CANCELED)
        {
            $this->logger->addWarning('Is active trial: '.$lastSubscription->getPackage()->getTitle());
            $this->logger->addWarning('Status: '.$lastSubscription->getStatus());

            $lastSubscription->setStatus(Subscription::STATUS_CANCELED);

            /** @var Job $cancelJob */
            $cancelJob = $this->jobManager->findOpenJobForRelatedEntity(CancelSubscriptionCommand::NAME, $lastSubscription);
            if (!is_null($cancelJob)) {
                $cancelJob->setState(Job::STATE_CANCELED);
                $this->em->persist($cancelJob);
            }

            $this->em->persist($lastSubscription);

            $this->disableExperiencesAfterDowngrade($entity, $lastSubscription->getPackage(), $user);
        }

        if (!$lastSubscription) {
            $user->setIsTrialUsed(true);
            $this->em->persist($user);
        }

        /** @var Subscription|null $previousSubscription */
        $previousSubscription = $this->subscriptionRepository->findOneBy([
            'appleOriginalTransactionId' => $result->getOriginalTransactionId()
        ]);

        $this->logger->addWarning('Is previous find: '.!is_null($previousSubscription));

        if (!is_null($previousSubscription)) {
            if ($previousSubscription->isActive()) {
                throw new BadRequestHttpException('You already have an active subscription on this account');
            } else {
                $previousSubscription->setStatus(Subscription::STATUS_CANCELED);
                $previousSubscription->setAppleOriginalTransactionId(null);
                $previousSubscription->setAppleOrderLineItemId(null);

                $this->disableExperiences($previousSubscription->getUser());

                $this->em->persist($previousSubscription);
                $this->em->flush();
            }
        }

        $transaction = new Transaction();
        $transaction->setTransactionId($result->getTransactionId());
        $transaction->setStatus(Transaction::STATUS_SUCCESS);
        $transaction->setUser($user);
        $transaction->setType(Transaction::TYPE_SUBSCRIPTION);
        $transaction->setAmount($entity->getPackage()->getPrice());
        $transaction->setProviderType(Subscription::PROVIDER_APPLE_IN_APP);

        $this->em->persist($transaction);

        $entity->setStatus(Subscription::STATUS_ACTIVE);
        $entity->setAppleReceipt($receipt);
        $entity->setAppleOrderLineItemId($result->getWebOrderLineItemId());

        $this->logger->addWarning('Carbon string: '. $result->getExpiresDate()->toString());
        $this->logger->addWarning('Carbon string: '. $result->getExpiresDate()->getTimestamp());

        $entity->setExpiresAt((new \DateTime())->setTimestamp($result->getExpiresDate()->getTimestamp()));
        $entity->setProviderType(Subscription::PROVIDER_APPLE_IN_APP);
        $entity->setAppleOriginalTransactionId($result->getOriginalTransactionId());
    }

    /**
     * @param Subscription $entity
     * @param User $user
     * @param PurchaseItem $result
     * @param string $receipt
     * @throws \Doctrine\ORM\ORMException
     * @throws \Money\UnknownCurrencyException
     */
    private function processInAppChangeSubscription(Subscription $entity, User $user, PurchaseItem $result, string $receipt): void
    {
        /** @var Package|null $nextPackage */
        $nextPackage = $entity->getNextPlan();

        if (is_null($nextPackage)) {
            throw new BadRequestHttpException('Next plan not selected');
        }

        if ($entity->getPackage()->getExperiencesNumber() < $nextPackage->getExperiencesNumber()) {
            $transaction = new Transaction();
            $transaction->setTransactionId($result->getTransactionId());
            $transaction->setStatus(Transaction::STATUS_SUCCESS);
            $transaction->setUser($user);
            $transaction->setType(Transaction::TYPE_SUBSCRIPTION);
            $transaction->setAmount($nextPackage->getPrice());
            $transaction->setProviderType(Subscription::PROVIDER_APPLE_IN_APP);

            $this->em->persist($transaction);

            $this->logger->addWarning('Carbon string: '. $result->getExpiresDate()->toString());
            $this->logger->addWarning('Carbon string: '. $result->getExpiresDate()->getTimestamp());

            $entity->setExpiresAt((new \DateTime())->setTimestamp($result->getExpiresDate()->getTimestamp()));
            $entity->setPackage($nextPackage);
            $entity->setNextPlan(null);
            $entity->setAppleDowngradeEnabled(null);
        } else {
            $entity->setNextPlan($nextPackage);
            $entity->setAppleDowngradeEnabled(true);
        }

        $entity->setStatus(Subscription::STATUS_ACTIVE);
        $entity->setAppleOrderLineItemId($result->getWebOrderLineItemId());
        $entity->setChangedPlanAt(new \DateTime());
        $entity->setIsAutorenew(true);
        $entity->setAppleReceipt($receipt);
    }

    /**
     * @param Subscription $subscription
     * @param string $receipt
     * @return PurchaseItem|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function validateReceipt(Subscription $subscription, string $receipt): ?PurchaseItem
    {
        /** @var ResponseInterface $result */
        $result = $this->appstoreService->receiptVerification(trim($receipt));
        $this->logger->warning('Number of purchase items: '.count($result->getLatestReceiptInfo()));

        $this->logger->addWarning('Is receipt is valid: '.$result->isValid());

        if (!$result->isValid()) {
            $this->logger->addWarning('Error code: '.$result->getResultCode());
            throw new BadRequestHttpException($result->getResultCode());
        }

        /** @var array $latestReceiptInfo */
        $latestReceiptInfo = $result->getLatestReceiptInfo();

        /** @var PurchaseItem|null $item */
        $item = $latestReceiptInfo[0];

        if (!is_null($subscription->getAppleReceipt())
            && $item->getOriginalTransactionId() !== $subscription->getAppleOriginalTransactionId())
        {
            throw new BadRequestHttpException('Wrong subscription!!!!');
        }

        $this->logger->addWarning('Receipt product: '.$item->getProductId());
        $this->logger->addWarning('local product: '.$subscription->getPackage()->getAppleProductId());

        /*if (
            ($subscription->getStatus() === Subscription::STATUS_PENDING
                && $item->getProductId() !== $subscription->getPackage()->getAppleProductId()) ||
            ($subscription->getStatus() === Subscription::STATUS_ACTIVE
                && $item->getProductId() === $subscription->getNextPlan()->getAppleProductId())
        )
        {
            throw new BadRequestHttpException('Wrong product!!!');
        }*/

        return $item;
    }

    /**
     * @param Subscription $subscription
     * @param string $receipt
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function validateAutoRenewReceipt(Subscription $subscription, string $receipt): array
    {
        /** @var AbstractResponse $result */
        $result = $this->appstoreService->receiptVerification(trim($receipt));

        if (!$result->isValid()) {
            throw new BadRequestHttpException($result->getResultCode());
        }

        /** @var array $latestReceiptInfo */
        $latestReceiptInfo = $result->getLatestReceiptInfo();
        $data = [];

        /** @var PurchaseItem $purchaseItem */
        foreach ($latestReceiptInfo as $purchaseItem) {
            if ($purchaseItem->getWebOrderLineItemId() === $subscription->getAppleOrderLineItemId()) {
                $data['purchaseInfo'] = $latestReceiptInfo[0];
            }
        }

        /** @var PendingRenewalInfo $pendingRenewalInfo */
        foreach ($result->getPendingRenewalInfo() as $pendingRenewalInfo) {
            if ($pendingRenewalInfo->getOriginalTransactionId() === $data['purchaseInfo']->getOriginalTransactionId()) {
                $data['pendingRenewalInfo'] = $pendingRenewalInfo;
            }
        }

        $data['receipt'] = $result->getReceipt();
        $data['rawData'] = $result->getRawData();

        return $data;
    }

    /**
     * @param Request $request
     * @param User $user
     * @param Subscription $subscription
     * @param string|null $platformType
     * @return SubscriptionProcessingResult
     * @throws \Exception
     */
    private function validatePackage(
        Request $request,
        User $user,
        Subscription $subscription,
        ?string $platformType = null): SubscriptionProcessingResult
    {
        $result = new SubscriptionProcessingResult();

        if (!$subscription->getPackage()->isPublic()) {
            $result->setMessage('You can use only public plans!');
            return $result;
        }

        if (!$subscription->getPackage()->isTrial()
            && !$request->request->has('payment_method_nonce')
            && $platformType !== ApiToken::SCOPE_IOS
        ) {
            $this->logger->addWarning('payment_method_nonce missing!!!');
            $result->setMessage('Something went wrong. Please try again later');
            return $result;
        }

        if (!$subscription->getPackage()->isTrial() &&
            ((is_null($platformType) || $platformType === ApiToken::SCOPE_ANDROID) && is_null($user->getCustomerId()))
        )
        {
            $this->logger->addWarning('customer Id missing');
            $result->setMessage('Something went wrong. Please try again later');
            return $result;
        }

        if ($user->isTrialUsed() && $subscription->getPackage()->isTrial()) {
            $result->setMessage('You have already used Trial plan. Please subscribe to a paid one.');
            return $result;
        }

        $activeSubscription = $user->getActiveSubscription();

        if ($activeSubscription && !$activeSubscription->getPackage()->isTrial()) {
            $result->setMessage('You already have active subscription');
            return $result;
        }

        $result->setSuccess(true);

        return $result;
    }

    /**
     * @param string $receipt
     * @return array|string
     */
    public function decodeReceipt(string $receipt)
    {
        $decodedReceipt = base64_decode(trim($receipt));
        if ($decodedReceipt === false) {
            throw new BadRequestHttpException('Receipt is invalid or fake');
        }

        $json = preg_replace('/=/', ':', $decodedReceipt);
        $json = preg_replace('/&quot;/', '"', $json);
        $json = preg_replace('/;/', ',', $json);
        $json = preg_replace('/&#10;/', '', $json);
        $json = preg_replace('/,(?:[^:]+)$/', '}', $json);

        $receiptData = json_decode($json,true);

        if (is_array($receiptData)) {
            $data = [];
            foreach ($receiptData as $key => $value) {
                $data[$key] = $this->decodeReceipt($value);
            }

            return $data;
        }

        return $receipt;
    }

    /**
     * @param Request $request
     * @return bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Money\UnknownCurrencyException
     * @throws \Exception
     */
    public function processInAppNotification(Request $request): bool
    {
        $this->logger->addInfo(json_encode($request->request->all()));

        /** array $notification */
        $notification = $request->request->all();
        
        if (!$notification || empty($notification)) {
            return false;
        }

        if ($notification['password'] !== $this->container->getParameter('appstore_password')) {
            return false;
        }

        $originalTransactionId = isset($notification['latest_receipt_info'])
            ? $notification['latest_receipt_info']['original_transaction_id']
            : $notification['latest_expired_receipt_info']['original_transaction_id'];

        /** @var Subscription $subscription */
        $subscription = $this->subscriptionRepository->findOneBy([
            'appleOriginalTransactionId' => $originalTransactionId,
            'providerType' => Subscription::PROVIDER_APPLE_IN_APP,
        ]);

        if (is_null($subscription)) {
            return false;
        }

        /** @var Package|null $package */
        $package = $this->packagesRepository->findOneBy(['appleProductId' => $notification['auto_renew_product_id']]);

        if (is_null($package)) {
            return false;
        }

        switch ($notification['notification_type']) {
            /**
             * This happens when the first subscription is purchased in a subscription group.
             * This type does basically what it says.
             */
            case 'INITIAL_BUY':

                /** @var Job $checkAppleSubscriptionJob */
                $checkAppleSubscriptionJob = $this->jobManager->findOpenJobForRelatedEntity(
                    CheckAutorenewSubscriptionCommand::NAME,
                    $subscription
                );

                if (is_null($checkAppleSubscriptionJob)) {
                    $checkAppleSubscriptionJob = new Job(CheckAutorenewSubscriptionCommand::NAME, [
                        sprintf('--%s=%d', CheckAutorenewSubscriptionCommand::SUBSCRIPTION_OBJECT_ID, $subscription->getId())
                    ]);

                    $checkAppleSubscriptionJob->addRelatedEntity($subscription);
                    $checkAppleSubscriptionJob->setExecuteAfter($subscription->getExpiresAt());

                    $this->em->persist($checkAppleSubscriptionJob);
                }

                break;
            /**
             * Indicates that the subscription was canceled either by Apple customer support 
             * or by the App Store when the user upgraded their subscription. 
             * This is also known colloquially as a refund.
             */
            case 'CANCEL':
                $subscription->setIsAutorenew(false);
                $jobCancelSubscription = new Job(CancelSubscriptionCommand::NAME, [
                    sprintf('--%s=%d', CancelSubscriptionCommand::SUBSCRIPTION_OBJECT_ID, $subscription->getId()),
                ]);

                $jobCancelSubscription->setExecuteAfter($subscription->getExpiresAt());
                $this->em->persist($jobCancelSubscription);

                /** @var Job $jobAutorenewChargeNotification */
                $jobAutorenewChargeNotification = $this->jobManager->findOpenJobForRelatedEntity(
                    AutorenewSubscriptionChargeNotificationCommand::NAME,
                    $subscription
                );

                if (!is_null($jobAutorenewChargeNotification)) {
                    $jobAutorenewChargeNotification->setState(Job::STATE_CANCELED);
                    $this->em->persist($jobAutorenewChargeNotification);
                }

                break;
            /**
             * RENEWAL events aren’t sent when a subscription autorenews.
             * RENEWAL is sent when a subscription has expired, then later, the user starts the subscription again.
             * Check expires_date to determine the next renewal date and time.
             */
            case 'RENEWAL':
            /**
             * Indicates the customer renewed a subscription interactively, either by using your app’s interface,
             * or on the App Store in account settings. Make service available immediately.
             */
            case 'INTERACTIVE_RENEWAL':
                /** @var User $user */
                $user = $subscription->getUser();

                /** @var Balance|null $balance */
                $balance = $user->getBalance();

                /** array $latestReceiptInfo */
                $latestReceiptInfo = isset($notification['latest_expired_receipt_info']) 
                    ? $notification['latest_expired_receipt_info'] 
                    : $notification['latest_receipt_info'];

                if (!$latestReceiptInfo) {
                    return false;
                }

                $subscription->setStatus(Subscription::STATUS_ACTIVE);

                $this->logger->addWarning('Timestamp raw: '. $latestReceiptInfo['expires_date']);
                $this->logger->addWarning('Timestamp converted: '. (int)$latestReceiptInfo['expires_date']/1000);

                /** @var \DateTime $expiresAt */
                $expiresAt = (new \DateTime())->setTimestamp((int)$latestReceiptInfo['expires_date']/1000);
                $expiresAt->setTimezone(new \DateTimeZone('UTC'));

                $this->logger->addWarning('Expire date renewal: '.$expiresAt->format('Y-m-d H:i:s:u'));

                $subscription->setExpiresAt($expiresAt);
                $subscription->setInitialBalanceAmount(is_null($balance) ? 0 : $balance->getAmount()->getAmount());

                //TODO: Possible can broke downgrade of subs, need retest
                if ($subscription->getPackage()->getId() !== $package->getId()) {
                    $subscription->setPackage($package);
                    $subscription->setChangedPlanAt(new \DateTime());
                }

                $this->em->persist($subscription);

                $freeLeft = $user->getViewsLeftFreeNumber($subscription);
                $paidLeft = $user->getViewsLeftPaidNumber($subscription);

                if ($freeLeft > 0 || $paidLeft > 0) {
                    $this->restoreExperiences($user, $subscription);
                }

                /** @var Job $jobAutorenewChargeNotification */
                $jobAutorenewChargeNotification = $this->jobManager->findOpenJobForRelatedEntity(
                    AutorenewSubscriptionChargeNotificationCommand::NAME,
                    $subscription
                );

                if (is_null($jobAutorenewChargeNotification)) {
                    $jobAutorenewChargeNotification = new Job(AutorenewSubscriptionChargeNotificationCommand::NAME, [
                        sprintf('--%s=%d', AutorenewSubscriptionChargeNotificationCommand::SUBSCRIPTION_OBJECT_ID, $subscription->getId()),
                    ]);

                    /** @var \DateTime $executeAt */
                    $executeAt = (clone $subscription->getExpiresAt())->sub(new \DateInterval('P3D'));

                    $jobAutorenewChargeNotification->addRelatedEntity($subscription);
                    $jobAutorenewChargeNotification->setExecuteAfter($executeAt);

                    $this->em->persist($jobAutorenewChargeNotification);
                }

                /** @var Job $checkAppleSubscriptionJob */
                $checkAppleSubscriptionJob = $this->jobManager->findOpenJobForRelatedEntity(
                    CheckAutorenewSubscriptionCommand::NAME,
                    $subscription
                );

                if (is_null($checkAppleSubscriptionJob)) {

                    $checkAppleSubscriptionJob = new Job(CheckAutorenewSubscriptionCommand::NAME, [
                        sprintf('--%s=%d', CheckAutorenewSubscriptionCommand::SUBSCRIPTION_OBJECT_ID, $subscription->getId())
                    ]);

                    $checkAppleSubscriptionJob->addRelatedEntity($subscription);
                    $checkAppleSubscriptionJob->setExecuteAfter($subscription->getExpiresAt());

                    $this->em->persist($checkAppleSubscriptionJob);
                }

                break;
            /**
             * Indicates the customer made a change in their subscription plan that takes effect at the next renewal.
             * The currently active plan is not affected.
             *
             * NOTICE: we don't process this notification
             */
            case 'DID_CHANGE_RENEWAL_PREF':
                return true;
                break;
            /**
             * Indicates a change in the subscription renewal status.
             */
            case 'DID_CHANGE_RENEWAL_STATUS':
                switch ((bool)$notification['auto_renew_status']) {
                    case true:
                        if ($subscription->isAutorenew()) {
                            return true;
                        }

                        $subscription->setIsAutorenew(true);
                        
                        /** @var Job $jobAutorenewChargeNotification */
                        $jobAutorenewChargeNotification = $this->jobManager->findOpenJobForRelatedEntity(
                            AutorenewSubscriptionChargeNotificationCommand::NAME,
                            $subscription
                        );

                        if (is_null($jobAutorenewChargeNotification)) {
                            $jobAutorenewChargeNotification = new Job(AutorenewSubscriptionChargeNotificationCommand::NAME, [
                                sprintf('--%s=%d', AutorenewSubscriptionChargeNotificationCommand::SUBSCRIPTION_OBJECT_ID, $subscription->getId()),
                            ]);

                            /** @var \DateTime $executeAt */
                            $executeAt = (clone $subscription->getExpiresAt())->sub(new \DateInterval('P3D'));

                            $jobAutorenewChargeNotification->addRelatedEntity($subscription);
                            $jobAutorenewChargeNotification->setExecuteAfter($executeAt);

                            $this->em->persist($jobAutorenewChargeNotification);
                        }

                        break;
                    case false:
                        if (!$subscription->isAutorenew()) {
                            return true;
                        }
                        
                        $subscription->setIsAutorenew(false);
                        $jobCancelSubscription = new Job(CancelSubscriptionCommand::NAME, [
                            sprintf('--%s=%d', CancelSubscriptionCommand::SUBSCRIPTION_OBJECT_ID, $subscription->getId()),
                        ]);

                        $jobCancelSubscription->setExecuteAfter($subscription->getExpiresAt());
                        $this->em->persist($jobCancelSubscription);

                        /** @var Job $jobAutorenewChargeNotification */
                        $jobAutorenewChargeNotification = $this->jobManager->findOpenJobForRelatedEntity(
                            AutorenewSubscriptionChargeNotificationCommand::NAME,
                            $subscription
                        );

                        if (!is_null($jobAutorenewChargeNotification)) {
                            $jobAutorenewChargeNotification->setState(Job::STATE_CANCELED);
                            $this->em->persist($jobAutorenewChargeNotification);
                        }

                        /** @var Job $jobCheckAutorenew */
                        $jobCheckAutorenew = $this->jobManager->findJobForRelatedEntity(
                            CheckAutorenewSubscriptionCommand::NAME,
                            $subscription
                        );

                        if ($jobCheckAutorenew) {
                            $jobCheckAutorenew->setState(Job::STATE_CANCELED);
                            $this->em->persist($jobCheckAutorenew);
                        }

                        break;
                }
                break;
            default:
                return false;
        }

        $receipt = isset($notification['latest_receipt'])
            ? $notification['latest_receipt']
            : $notification['latest_expired_receipt'];

        $subscription->setAppleReceipt($receipt);

        $this->em->persist($subscription);
        $this->em->flush();

        return true;
    }

    /**
     * @param int $subscriptionId
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Money\UnknownCurrencyException
     * @throws \Exception
     */
    public function checkAppleAutorenewSubscription(int $subscriptionId): void
    {
        /** @var Subscription|null $subscription */
        $subscription = $this->subscriptionRepository
            ->findSubscriptionForAutorenew($subscriptionId,Subscription::PROVIDER_APPLE_IN_APP);

        if (is_null($subscription)) {
            return;
        }

        /** @var array $response */
        $response = $this->validateAutoRenewReceipt($subscription, $subscription->getAppleReceipt());

        $this->logger->addInfo(json_encode($response));

        /** @var PurchaseItem $purchaseInfo */
        $purchaseInfo = $response['purchaseInfo'];

        /** @var PendingRenewalInfo $pendingRenewalInfo */
        $pendingRenewalInfo = $response['pendingRenewalInfo'];

        $originalTransactionId = is_null($purchaseInfo)
            ? $response['receipt']['original_transaction_id']
            : $purchaseInfo->getOriginalTransactionId();

        if ($subscription->getAppleOriginalTransactionId() !== $originalTransactionId) {
            return;
        }

        $productId = is_null($purchaseInfo)
            ? $response['receipt']['product_id']
            : $purchaseInfo->getProductId();

        /** @var Package|null $package */
        $package = $this->packagesRepository->findOneBy(['appleProductId' => $productId]);

        if (is_null($package)) {
            return;
        }

        $isInBillingRetryPeriod = is_null($pendingRenewalInfo)
            ? (int)$response['rawData']['is_in_billing_retry_period']
            : $pendingRenewalInfo->isInBillingRetryPeriod();

        $expirationIntent = is_null($pendingRenewalInfo)
            ? (int)$response['rawData']['expiration_intent']
            : $pendingRenewalInfo->getExpirationIntent();

        /**
         * Subscription Retry Flag - is_in_billing_retry_period
         * For an expired subscription, whether or not Apple is still attempting to automatically renew the subscription.
         *
         * Subscription Expiration Intent - expiration_intent
         * For an expired subscription, the reason for the subscription expiration.
         */
        if (!is_null($isInBillingRetryPeriod) &&
            in_array($expirationIntent,Subscription::INTENT_ERRORS))
        {
            /** @var User $user */
            $user = $subscription->getUser();

            if ((int)$isInBillingRetryPeriod === 0) {
                $subscription->setStatus(Subscription::STATUS_CANCELED);
                $subscription->setAppleReceipt($response['rawData']['latest_receipt']);
                $subscription->setAppleOrderLineItemId($response['rawData']['latest_expired_receipt_info']['web_order_line_item_id']);
                $subscription->setIsAutorenew(false);

                $this->disableSubscription($subscriptionId);

                $this->em->persist($subscription);
                $this->em->flush();

                return;
            }

            switch ($expirationIntent) {
                case PendingRenewalInfo::EXPIRATION_INTENT_INCREASE_DECLINED:
                case PendingRenewalInfo::EXPIRATION_INTENT_CANCELLED:

                    $subscription->setIsAutorenew(false);
                    $jobCancelSubscription = new Job(CancelSubscriptionCommand::NAME, [
                        sprintf('--%s=%d', CancelSubscriptionCommand::SUBSCRIPTION_OBJECT_ID, $subscription->getId()),
                    ]);

                    $jobCancelSubscription->setExecuteAfter($subscription->getExpiresAt());
                    $this->em->persist($jobCancelSubscription);

                    /** @var Job $jobAutorenewChargeNotification */
                    $jobAutorenewChargeNotification = $this->jobManager->findOpenJobForRelatedEntity(
                        AutorenewSubscriptionChargeNotificationCommand::NAME,
                        $subscription
                    );

                    if (!is_null($jobAutorenewChargeNotification)) {
                        $jobAutorenewChargeNotification->setState(Job::STATE_CANCELED);
                        $this->em->persist($jobAutorenewChargeNotification);
                    }
                    break;
                case PendingRenewalInfo::EXPIRATION_INTENT_BILLING_ERROR:

                    $subscription->setStatus(Subscription::STATUS_CHARGED_UNSUCCESSFULLY);
                    $this->disableExperiences($user);

                    $this->notificationService->sendSubscriptionAutorenewChargeFailed($user);

                    /** @var Job $checkAppleSubscriptionJob */
                    $checkAppleSubscriptionJob = new Job(CheckAutorenewSubscriptionCommand::NAME, [
                        sprintf('--%s=%d', CheckAutorenewSubscriptionCommand::SUBSCRIPTION_OBJECT_ID, $subscription->getId())
                    ]);

                    $checkAppleSubscriptionJob->addRelatedEntity($subscription);
                    $checkAppleSubscriptionJob->setExecuteAfter((new \DateTime())->sub(new \DateInterval('P1D')));

                    $this->em->persist($checkAppleSubscriptionJob);

                    break;
                case PendingRenewalInfo::EXPIRATION_INTENT_PRODUCT_UNAVAILABLE:
                case PendingRenewalInfo::EXPIRATION_INTENT_UNKNOWN:

                    $subscription->setStatus(Subscription::STATUS_PAST_DUE);
                    $this->disableExperiences($user);

                    $this->notificationService->sendSubscriptionAutorenewFailed($user);

                    /** @var Job $checkAppleSubscriptionJob */
                    $checkAppleSubscriptionJob = new Job(CheckAutorenewSubscriptionCommand::NAME, [
                        sprintf('--%s=%d', CheckAutorenewSubscriptionCommand::SUBSCRIPTION_OBJECT_ID, $subscription->getId())
                    ]);

                    $checkAppleSubscriptionJob->addRelatedEntity($subscription);
                    $checkAppleSubscriptionJob->setExecuteAfter((new \DateTime())->sub(new \DateInterval('P1D')));

                    $this->em->persist($checkAppleSubscriptionJob);

                    break;
            }

            $subscription->setAppleReceipt($response['rawData']['latest_receipt']);
            $subscription->setAppleOrderLineItemId($response['rawData']['latest_receipt_info']['web_order_line_item_id']);

            $this->em->persist($subscription);
            $this->em->flush();

            return;
        }

        $autoRenewStatus = is_null($pendingRenewalInfo)
            ? (int)$response['rawData']['auto_renew_status']
            : $pendingRenewalInfo->getAutoRenewStatus();

        $this->logger->addWarning('autoRenewStatus: '.$autoRenewStatus);

        /**
         * Customer has turned off automatic renewal for their subscription.
         */
        if ($autoRenewStatus === 0 || !$autoRenewStatus) {
            $subscription->setIsAutorenew(false);
            $subscription->setAppleOrderLineItemId($response['rawData']['latest_receipt_info']['web_order_line_item_id']);
            $subscription->setAppleReceipt($response['rawData']['latest_receipt']);

            $jobCancelSubscription = new Job(CancelSubscriptionCommand::NAME, [
                sprintf('--%s=%d', CancelSubscriptionCommand::SUBSCRIPTION_OBJECT_ID, $subscription->getId()),
            ]);

            $jobCancelSubscription->setExecuteAfter($subscription->getExpiresAt());
            $this->em->persist($jobCancelSubscription);

            /** @var Job $jobAutorenewChargeNotification */
            $jobAutorenewChargeNotification = $this->jobManager->findOpenJobForRelatedEntity(
                AutorenewSubscriptionChargeNotificationCommand::NAME,
                $subscription
            );

            if (!is_null($jobAutorenewChargeNotification)) {
                $jobAutorenewChargeNotification->setState(Job::STATE_CANCELED);
                $this->em->persist($jobAutorenewChargeNotification);
            }

            $this->em->persist($subscription);
            $this->em->flush();

            return;
        }

        /** @var int $expiresDateTimestamp */
        $expiresDateTimestamp = is_null($purchaseInfo)
            ? (int)$response['receipt']['expires_date']/1000
            : $purchaseInfo->getExpiresDate()->getTimestamp();

        if (is_null($purchaseInfo)) {
            $this->logger->addWarning('Expires date raw: '.$response['receipt']['expires_date']);
            $this->logger->addWarning('Expires date raw converted:'.(int)$response['receipt']['expires_date']/1000);
        } else {
            $this->logger->addWarning('Expires date carbon string: '.$purchaseInfo->getExpiresDate()->toString());
            $this->logger->addWarning('Expires date carbon timestamp: '.$purchaseInfo->getExpiresDate()->getTimestamp());
        }

        /**
         * If subscription successfully renewed
         */
        if ($autoRenewStatus === 1 || $autoRenewStatus)
        {
            /** @var User $user */
            $user = $subscription->getUser();

            /** @var Balance|null $balance */
            $balance = $user->getBalance();

            $subscription->setStatus(Subscription::STATUS_ACTIVE);

            /** @var \DateTime $expiresAt */
            $expiresAt = (new \DateTime())->setTimestamp($expiresDateTimestamp);
            $expiresAt->setTimezone(new \DateTimeZone('UTC'));

            $this->logger->addWarning('Expire date renewal: '.$expiresAt->format('Y-m-d H:i:s:u'));

            $subscription->setExpiresAt($expiresAt);

            $subscription->setInitialBalanceAmount(is_null($balance) ? 0 : $balance->getAmount()->getAmount());
            $subscription->setAppleOrderLineItemId($response['rawData']['latest_receipt_info']['web_order_line_item_id']);
            $subscription->setAppleReceipt($response['rawData']['latest_receipt']);

            /** @var Package $nextPlan */
            $nextPlan = $subscription->getNextPlan();

            if (!is_null($subscription->getAppleDowngradeEnabled()) && $nextPlan)
            {
                /** @var Package $previousPackage */
                $previousPackage = $subscription->getPackage();

                /** Downgrade */
                if ($subscription->getAppleDowngradeEnabled()) {
                    $subscription->setPackage($nextPlan);
                    $subscription->setAppleDowngradeEnabled(null);
                    $subscription->setNextPlan(null);

                    $this->disableExperiencesAfterDowngrade($subscription, $previousPackage, $user);
                    $this->notificationService->sendSubscriptionDeferredUpdate($user, $nextPlan->getDescription());
                }
            } else {
                /** @var string $transactionId */
                $transactionId = is_null($purchaseInfo) ? $response['receipt']['transaction_id'] : $purchaseInfo->getTransactionId();

                /** @var Transaction|null $transaction */
                $transaction = $this->transactionRepository->findOneBy([
                    'transactionId' => $transactionId
                ]);

                if (is_null($transaction)) {
                    $transaction = new Transaction();
                    $transaction->setTransactionId($response['rawData']['latest_receipt_info']['transaction_id']);
                    $transaction->setStatus(Transaction::STATUS_SUCCESS);
                    $transaction->setUser($user);
                    $transaction->setType(Transaction::TYPE_SUBSCRIPTION);
                    $transaction->setAmount($subscription->getPackage()->getPrice());
                    $transaction->setProviderType(Subscription::PROVIDER_APPLE_IN_APP);

                    $this->em->persist($transaction);
                }
            }

            $this->em->persist($subscription);
            $this->em->flush();

            $freeLeft = $user->getViewsLeftFreeNumber($subscription);
            $paidLeft = $user->getViewsLeftPaidNumber($subscription);

            if ($freeLeft > 0 || $paidLeft > 0) {
                $this->restoreExperiences($user, $subscription);
            }

            /** @var Job $jobAutorenewChargeNotification */
            $jobAutorenewChargeNotification = $this->jobManager->findOpenJobForRelatedEntity(
                AutorenewSubscriptionChargeNotificationCommand::NAME,
                $subscription
            );

            if (is_null($jobAutorenewChargeNotification)) {
                $jobAutorenewChargeNotification = new Job(AutorenewSubscriptionChargeNotificationCommand::NAME, [
                    sprintf('--%s=%d', AutorenewSubscriptionChargeNotificationCommand::SUBSCRIPTION_OBJECT_ID, $subscription->getId()),
                ]);

                $jobAutorenewChargeNotification->addRelatedEntity($subscription);
                $jobAutorenewChargeNotification->setExecuteAfter(
                    $expiresAt->sub(new \DateInterval('P3D'))
                );

                $this->em->persist($jobAutorenewChargeNotification);
            }

            /** @var Job $checkAppleSubscriptionJob */
            $checkAppleSubscriptionJob = new Job(CheckAutorenewSubscriptionCommand::NAME, [
                sprintf('--%s=%d', CheckAutorenewSubscriptionCommand::SUBSCRIPTION_OBJECT_ID, $subscription->getId())
            ]);

            $checkAppleSubscriptionJob->addRelatedEntity($subscription);
            $checkAppleSubscriptionJob->setExecuteAfter($subscription->getExpiresAt());

            $this->em->persist($checkAppleSubscriptionJob);

            $this->notificationService->sendSubscriptionAutorenew($user);

            $this->em->flush();

            return;
        }
    }

    /**
     * @param int  $page
     * @param int  $perPage
     * @return array
     * @throws \Exception
     */
    public function listProducts(int $page, int $perPage): array
    {
        /** @var array $array */
        $array = $this->productRepository->findAll();

        $adapter = new ArrayAdapter($array);
        $paginator = new Pagerfanta($adapter);

        $paginator->setAllowOutOfRangePages(true);

        $paginator->setMaxPerPage($perPage);
        $paginator->setCurrentPage($page);

        $pagerArray = [
            "count" => $paginator->getNbResults(),
            "pageCount" => $paginator->getNbPages(),
            "products" => (array)$paginator->getCurrentPageResults()
        ];

        return $pagerArray;
    }

    /**
     * @param Subscription $subscription
     * @param User $user
     * @param string $receipt
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Money\UnknownCurrencyException
     */
    public function validatePaymentReceipt(Subscription $subscription, User $user, string $receipt)
    {
        /** @var PurchaseItem|null $result */
        $result = $this->validateReceipt($subscription, $receipt);

        if (is_null($result)) {
            throw new BadRequestHttpException('Wrong receipt or purchases.');
        }

        /** @var \DateTime $expiresDate */
        $expiresDate = (new \DateTime())->setTimestamp($result->getExpiresDate()->getTimestamp());

        if ($expiresDate < new \DateTime()) {
            throw new BadRequestHttpException('Expire date in the past!');
        }

        /**
         * Validating assign
         */
        if ($subscription->getStatus() === Subscription::STATUS_PENDING
            && $result->getProductId() === $subscription->getPackage()->getAppleProductId()
            && is_null($subscription->getAppleOriginalTransactionId())
            && is_null($subscription->getAppleReceipt())
        ) {
            $this->logger->addWarning('Starting assign for: '.$subscription->getId());
            $this->processInAppAssignSubscription($subscription, $user, $result, $receipt);

            $this->em->persist($subscription);
            $this->em->flush();

            return;
        }

        /**
         * Validating change
         */
        if ($subscription->getStatus() === Subscription::STATUS_ACTIVE
            && !is_null($subscription->getNextPlan())
            && !is_null($subscription->getAppleDowngradeEnabled())
            && $subscription->getAppleOriginalTransactionId() === $result->getOriginalTransactionId()
            && $result->getProductId() === $subscription->getNextPlan()->getAppleProductId())
        {
            $this->logger->addWarning('Starting change for: '.$subscription->getId());
            $this->processInAppChangeSubscription($subscription, $user, $result, $receipt);

            $this->em->persist($subscription);
            $this->em->flush();

            return;
        }
    }

    /**
     * @throws \Exception
     */
    public function deleteRejectedExperiences(): void
    {
        $pendingSubscriptions = $this->subscriptionRepository->findPendingSubscriptions();

        /** @var Subscription $pendingSubscription */
        foreach ($pendingSubscriptions as $pendingSubscription) {
            $this->em->remove($pendingSubscription);
        }

        $this->em->flush();
    }

    /**
     * @param Subscription $subscription
     * @param Package $previousPackage
     * @param User $user
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function disableExperiencesAfterDowngrade(Subscription $subscription, Package $previousPackage, User $user): void
    {
        if ($previousPackage->getExperiencesNumber() > $subscription->getPackage()->getExperiencesNumber()) {
            $count = 0;

            $experiences = $user->getExperience();
            foreach ($experiences as $experience) {
                if ($experience->getStatus() === Experience::EXPERIENCE_ACTIVE) {
                    ++$count;

                    if ($count > $subscription->getPackage()->getExperiencesNumber()) {
                        $experience->setStatus(Experience::EXPERIENCE_DISABLED);
                        $this->em->persist($experience);

                        $this->vuforiaService->updateTarget($experience,0);
                    }
                }
            }
            $this->em->flush();
        }
    }

    /**
     * @param User $user
     * @param string|null $receipt
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function checkIsAppleAccountAlreadyInUse(User $user, ?string $receipt = null): array
    {
        /** @var PurchaseItem|null $item */
        $item = null;

        if (!is_null($receipt)) {
            /** @var ResponseInterface $result */
            $result = $this->appstoreService->receiptVerification(trim($receipt));

            $this->logger->warning('Number of purchase items: '.count($result->getLatestReceiptInfo()));
            $this->logger->addWarning('Is receipt is valid: '.$result->isValid());

            if (!$result->isValid()) {
                $this->logger->addWarning('Error code: '.$result->getResultCode());
                throw new BadRequestHttpException($result->getResultCode());
            }

            /** @var array $latestReceiptInfo */
            $latestReceiptInfo = $result->getLatestReceiptInfo();

            /** @var PurchaseItem|null $item */
            $item = isset($latestReceiptInfo[0]) ? $latestReceiptInfo[0] : null;
        }

        /** @var Subscription|bool $currentUserSubscription */
        $currentUserSubscription = $user->getLastSubscription();

        if ($currentUserSubscription && !$currentUserSubscription->getPackage()->isTrial()) {
            if (!is_null($currentUserSubscription->getAppleOriginalTransactionId()) && is_null($item))
            {
                return [
                    'isAnotherUser' => true,
                    'isExist' => false,
                    'isNextStepAllowed' => false,
                    'isActive' => false,
                ];
            }

            if (!is_null($currentUserSubscription->getAppleOriginalTransactionId())
                && !is_null($item)
                && $currentUserSubscription->isActive()
                && $currentUserSubscription->getAppleOriginalTransactionId() !== $item->getOriginalTransactionId())
            {
                return [
                    'isAnotherUser' => true,
                    'isExist' => true,
                    'isNextStepAllowed' => false,
                    'isActive' => true,
                ];
            }

            if (is_null($item) && $currentUserSubscription->getProviderType() === Subscription::PROVIDER_BRAINTREE) {
                return [
                    'isAnotherUser' => false,
                    'isExist' => true,
                    'isActive' => false,
                    'isNextStepAllowed' => true
                ];
            }
        }

        if (is_null($item) && $currentUserSubscription !== false && $currentUserSubscription->getPackage()->isTrial()) {
            return [
                'isAnotherUser' => false,
                'isExist' => false,
                'isActive' => false,
                'isNextStepAllowed' => true
            ];
        }

        if (is_null($item) && $currentUserSubscription === false) {
            return [
                'isAnotherUser' => false,
                'isExist' => false,
                'isActive' => false,
                'isNextStepAllowed' => true
            ];
        }

        //TODO: Implement constraint for trial current subs && subs form receipt already expired ??????

        /** @var Subscription|null $previousSubscription */
        $previousSubscription = $this->subscriptionRepository->findOneBy([
            'appleOriginalTransactionId' => $item->getOriginalTransactionId()
        ]);

        $this->logger->addWarning('Is previous find: '.!is_null($previousSubscription));

        if (is_null($previousSubscription)) {
            return [
                'isAnotherUser' => false,
                'isExist' => false,
                'isActive' => false,
                'isNextStepAllowed' => true
            ];
        }

        if ($previousSubscription->getStatus() === Subscription::STATUS_ACTIVE
            && $previousSubscription->getUser() !== $user)
        {
            return [
                'isAnotherUser' => false,
                'isExist' => true,
                'isActive' => true,
                'isNextStepAllowed' => false
            ];
        }

        return [
            'isAnotherUser' => false,
            'isExist' => true,
            'isActive' => false,
            'isNextStepAllowed' => true
        ];
    }
}
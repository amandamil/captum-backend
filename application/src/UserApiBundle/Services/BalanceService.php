<?php

namespace UserApiBundle\Services;

use CoreBundle\{
    Exception\FormValidationException,
    Services\ResponseService
};
use Doctrine\ORM\{
    EntityManager,
    EntityManagerInterface
};
use Money\{
    Currency,
    Money
};
use Pagerfanta\{
    Adapter\ArrayAdapter,
    Pagerfanta
};
use Symfony\Component\{
    Form\FormFactoryInterface,
    HttpFoundation\Request,
    HttpKernel\Exception\BadRequestHttpException
};
use UserApiBundle\{Entity\ApiToken,
    Entity\Balance,
    Entity\Charge,
    Entity\User,
    Form\AmountType,
    Form\InAppForm\AmountInAppType,
    Form\LimitType,
    Model\Amount,
    Model\InAppModel\AmountInApp,
    Model\Limit,
    Repository\BalanceRepository,
    Repository\ChargeRepository};
use Monolog\Logger;
use Symfony\Component\DependencyInjection\ContainerInterface;
use SubscriptionBundle\{Entity\Subscription,
    Repository\TransactionRepository,
    Services\AppstoreService,
    Services\BraintreeService,
    Entity\Transaction,
    Services\SubscriptionService};
use ReceiptValidator\iTunes\{
    PurchaseItem,
    ResponseInterface
};

/**
 * Class BalanceService
 * @package UserApiBundle\Services
 */
class BalanceService
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

    /** @var BalanceRepository $balanceRepository */
    private $balanceRepository;

    /** @var TransactionRepository $transactionRepository */
    private $transactionRepository;

    /** @var SubscriptionService $subscriptionService */
    private $subscriptionService;

    /** @var ChargeRepository */
    private $chargeRepository;

    /** @var AppstoreService $appstoreService */
    private $appstoreService;

    /** @var Logger $logger */
    private $logger;

    /**
     * BalanceService constructor.
     * @param EntityManagerInterface $em
     * @param ContainerInterface $container
     * @param BraintreeService $braintreeService
     * @param FormFactoryInterface $formFactory
     * @param ResponseService $responseService
     * @param SubscriptionService $subscriptionService
     * @param AppstoreService $appstoreService
     * @param Logger $logger
     */
    public function __construct(
        EntityManagerInterface $em,
        ContainerInterface $container,
        BraintreeService $braintreeService,
        FormFactoryInterface $formFactory,
        ResponseService $responseService,
        SubscriptionService $subscriptionService,
        AppstoreService $appstoreService,
        Logger $logger
    )
    {
        $this->em = $em;
        $this->container = $container;
        $this->braintreeService = $braintreeService;
        $this->formFactory = $formFactory;
        $this->responseService = $responseService;
        $this->subscriptionService = $subscriptionService;
        $this->balanceRepository = $this->em->getRepository('UserApiBundle:Balance');
        $this->transactionRepository = $this->em->getRepository('SubscriptionBundle:Transaction');
        $this->chargeRepository = $this->em->getRepository(Charge::class);
        $this->appstoreService = $appstoreService;
        $this->logger = $logger;
    }

    /**
     * @param Request $request
     * @param User $user
     * @return Balance
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Money\UnknownCurrencyException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function fillBalance(Request $request, User $user): Balance
    {
        $scope = $request->headers->get('scope');

        $balanceAmountModel = new Amount();
        $form = $this->formFactory->create(AmountType::class, $balanceAmountModel);

        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            throw new BadRequestHttpException('Wrong Request');
        }

        if (!$form->isValid()) {
            throw new FormValidationException($this->responseService->getFormError($form));
        }

        /** @var Subscription $subscription */
        $subscription = $user->getActiveSubscription();

        /** @var \DateTime $previousChangeAt */
        $previousChangeAt = is_null($subscription->getChangedPlanAt())
            ? $subscription->getCreatedAt()
            : $subscription->getChangedPlanAt();

        $minutes = $this->getDiff($previousChangeAt);

        if ($scope === ApiToken::SCOPE_ANDROID && $minutes < 1) {
            throw new BadRequestHttpException('You must wait at least 1 minute after plan changes');
        }

        /** @var Balance|null $balance */
        $balance = $user->getBalance();

        if ($balance) {
            /** @var \DateTime $previousRefill */
            $previousRefill = is_null($balance->getRefillBalanceAt())
                ? $balance->getCreatedAt()
                : $balance->getRefillBalanceAt();

            $minutes = $this->getDiff($previousRefill);

            if ($scope === ApiToken::SCOPE_ANDROID && $minutes < 1) {
                throw new BadRequestHttpException('You must wait at least 1 minute after refill balance');
            }
        }

        switch ($scope) {
            case null:
            case ApiToken::SCOPE_ANDROID:
                $this->processBraintreeBalancePurchase(
                    $user,
                    $balanceAmountModel->getBalanceAmount(),
                    $balanceAmountModel->getPaymentMethodNonce())
                ;
                break;
            case ApiToken::SCOPE_IOS:
                $this->processInAppBalancePurchase(
                    $user,
                    $balanceAmountModel->getBalanceAmount(),
                    $balanceAmountModel->getPaymentMethodNonce());
                break;
        }

        switch (is_null($balance)) {
            case true:
                $money = new Money((int)$balanceAmountModel->getBalanceAmount()*100, new Currency('USD'));

                $balance = new Balance();
                $balance->setAmount($money);
                $balance->setUser($user);
                break;
            case false:
                $balancePreviousBalance = $balance->getAmount()->getAmount();
                $amount = $balancePreviousBalance + $balanceAmountModel->getBalanceAmount()*100;
                $money = new Money((int)$amount, new Currency('USD'));

                $balance->setAmount($money);
                $balance->setRefillBalanceAt(new \DateTime());

                if ($balancePreviousBalance <= 0 && $amount > 0) {
                    $this->subscriptionService->restoreExperiences($user, $subscription);
                }
                break;
        }

        $subscription->setInitialBalanceAmount(
            $subscription->getInitialBalanceAmount() + ((int)$balanceAmountModel->getBalanceAmount()*100)
        );

        $this->em->persist($subscription);
        $this->em->persist($balance);
        $this->em->flush();

        return $balance;
    }

    /**
     * @param Request $request
     * @param User $user
     * @return Balance
     * @throws \Doctrine\ORM\ORMException
     * @throws \Money\UnknownCurrencyException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function fillBalanceLimit(Request $request, User $user): Balance
    {
        $limitModel = new Limit();
        $form = $this->formFactory->create(LimitType::class, $limitModel);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            throw new BadRequestHttpException('Wrong Request');
        }

        if (!$form->isValid()) {
            throw new FormValidationException($this->responseService->getFormError($form));
        }

        /** @var Subscription|null $subscription */
        $subscription = $user->getActiveSubscription();

        /** @var Balance $balance */
        $balance = $user->getBalance();

        if (is_null($balance)) {
            throw new BadRequestHttpException('Please refill your balance before setting a monthly limit');
        }

        /** @var Money $previousLimit */
        $previousLimit = $balance->getMonthlyLimit();

        $balance->setIsChargeLimitEnabled($limitModel->isChargeLimitEnabled());

        if ($limitModel->isChargeLimitEnabled()) {
            $newLimitCents = (int)($limitModel->getMonthlyLimit()*100);

            $balance->setMonthlyLimit(is_null($limitModel->getMonthlyLimit())
                ? $previousLimit
                : new Money($newLimitCents, new Currency('USD')));

            $balance->setIsLimitWarningEnabled(is_null($limitModel->getWarnLimitReached())
                ? $balance->isLimitWarningEnabled()
                : $limitModel->getWarnLimitReached());

            if ($previousLimit->getAmount() > $newLimitCents && $user->getSubscriptionPeriodCharges($subscription) > $newLimitCents) {
                $this->subscriptionService->disableExperiences($user);
            }

            // TODO: recheck with subscription period charges
            if ($balance->getMonthlyLimit()->getAmount() > $previousLimit->getAmount()) {
                $this->subscriptionService->restoreExperiences($user, $subscription);
            }
        }

        $this->em->persist($balance);
        $this->em->flush();

        return $balance;
    }

    /**
     * @param User $user
     * @param int $page
     * @param int $perPage
     * @return array
     * @throws \Doctrine\ORM\Query\QueryException
     */
    public function purchaseHistory(User $user, int $page, int $perPage): array
    {
        $query = $this->transactionRepository->getUserPurchaseHistory($user);

        $adapter = new ArrayAdapter($query);
        $paginator = new Pagerfanta($adapter);

        $paginator->setAllowOutOfRangePages(true);

        $paginator->setCurrentPage($page);
        $paginator->setMaxPerPage($perPage);

        $pagerArray = [
            "count" => $paginator->getNbResults(),
            "pageCount" => $paginator->getNbPages(),
            "transactions" => (array)$paginator->getCurrentPageResults()
        ];

        return $pagerArray;
    }

    /**
     * Get initial balance since last refill
     * @param User $user
     * @return int
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Money\UnknownCurrencyException
     */
    public function getInitBalanceSinceLastRefillAmount(User $user)
    {
        $balance = $user->getBalance();
        $lastTransaction = $this->transactionRepository->findLastByUser($user);

        if (!$lastTransaction) {
            return 0;
        }
        $charges = $this->chargeRepository->findByBalanceRangeDate($balance, $lastTransaction->getUpdatedAt(), new \DateTime());

        $initBalanceAmount = $balance->getAmount()->getAmount();
        foreach ($charges as $charge) {
            $initBalanceAmount += $charge->getAmount()->getAmount();
        }

        return $initBalanceAmount;
    }

    /**
     * @param object $resultTransaction
     * @param User $user
     * @return void
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Money\UnknownCurrencyException
     */
    private function storeTransaction($resultTransaction, User $user): void
    {
        $transaction = new Transaction();
        $transaction->setTransactionId($resultTransaction->id);
        $transaction->setStatus(isset($resultTransaction->status) ? $resultTransaction->status : null);
        $transaction->setUser($user);
        $transaction->setType(Transaction::TYPE_BALANCE);
        $transaction->setAmount(new Money((int)($resultTransaction->amount*100), new Currency('USD')));
        $transaction->setProviderType(Subscription::PROVIDER_BRAINTREE);

        $this->em->persist($transaction);
        $this->em->flush();
    }

    /**
     * @param \DateTime $previous
     * @return int
     * @throws \Exception
     */
    private function getDiff(\DateTime $previous): int
    {
        $diff = $previous->diff(new \DateTime());
        return ((int)$diff->format('%a') * 1440) + ((int)$diff->format('%h') * 60) + (int)$diff->format('%i');
    }

    /**
     * @param User $user
     * @param float $amount
     * @param string $receipt
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Money\UnknownCurrencyException
     */
    private function processInAppBalancePurchase(User $user, float $amount, string $receipt): void
    {
        /** @var ResponseInterface $result */
        $result = $this->appstoreService->receiptVerification(trim($receipt));

        if (!$result->isValid()) {
            throw new BadRequestHttpException($result->getResultCode());
        }

        /** @var PurchaseItem $purchaseItem */
        $purchaseItem = $result->getLatestReceiptInfo()[0];

        $this->logger->addWarning($purchaseItem->getProductId());
        $this->logger->addWarning($result->getReceipt()['in_app'][0]['product_id']);

        if (!in_array($result->getReceipt()['in_app'][0]['product_id'],Balance::VIEW_BUCKS)) {
            throw new BadRequestHttpException('Wrong product!!!');
        }

        if (!in_array((int)$amount, Balance::$amounts)) {
            throw new BadRequestHttpException('Wrong amount!!!');
        }

        $transaction = new Transaction();
        $transaction->setTransactionId($purchaseItem->getTransactionId());
        $transaction->setStatus($result->isValid() ? Transaction::STATUS_SETTLED : $result->getResultCode());
        $transaction->setUser($user);
        $transaction->setType(Transaction::TYPE_BALANCE);
        $transaction->setAmount(new Money((int)($amount*100), new Currency('USD')));
        $transaction->setProviderType(Subscription::PROVIDER_APPLE_IN_APP);

        $this->em->persist($transaction);
        $this->em->flush();
    }

    /**
     * @param User $user
     * @param float $amount
     * @param string $nonce
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Money\UnknownCurrencyException
     */
    private function processBraintreeBalancePurchase(User $user, float $amount, string $nonce): void
    {
        $paymentResult = $this->braintreeService->saleTransaction($nonce, $amount);
        $this->storeTransaction($paymentResult->transaction, $user);

        if (!$paymentResult->success) {
            throw new BadRequestHttpException('Something went wrong. Please try again later');
        }
    }
}

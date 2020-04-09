<?php

namespace UserApiBundle\Services;

use CoreBundle\{
    Exception\FormValidationException,
    Services\ResponseService
};
use Doctrine\ORM\{
    EntityManager,
    EntityManagerInterface,
    OptimisticLockException,
    ORMException
};
use MailgunBundle\Services\MailgunService;
use SubscriptionBundle\Entity\Subscription;
use SubscriptionBundle\Services\BraintreeService;
use Symfony\Component\{
    DependencyInjection\ContainerInterface,
    Form\FormFactoryInterface,
    HttpFoundation\Request,
    HttpKernel\Exception\BadRequestHttpException,
    HttpKernel\Exception\NotFoundHttpException,
    HttpKernel\Exception\UnauthorizedHttpException,
    Security\Core\Encoder\UserPasswordEncoderInterface
};
use UserApiBundle\{
    Entity\ApiToken,
    Entity\Client,
    Entity\User,
    Entity\VerificationCode,
    Form\ForgotPasswordType,
    Form\PasswordChangeType,
    Form\UserType,
    Model\ChangeForgotPassword,
    Form\UserUpdateType,
    Model\ChangePassword,
    Model\VerificationCodeStatus,
    Model\VerificationCodeType,
    Repository\ApiTokenRepository,
    Repository\ClientRepository,
    Repository\UserRepository,
    Repository\VerificationCodeRepository
};
use Swift_Mailer;

/**
 * Class AuthService
 * @package UserApiBundle\Services
 */
class AuthService
{
    /*const FROM = 'captum@captumapp.com';*/

    /** @var EntityManager */
    private $em;

    /** @var ContainerInterface $container */
    private $container;

    /** @var VerificationCodeRepository $verificationRepository */
    private $verificationRepository;

    /** @var FormFactoryInterface $formFactory */
    private $formFactory;

    /** @var UserPasswordEncoderInterface $encoder */
    private $encoder;

    /** @var MailgunService $mailgunService */
    private $mailgunService;

    /** @var AwsSesService $awsSesService */
    private $awsSesService;

    /** @var ResponseService $responseService */
    private $responseService;

    /** @var UserRepository $userRepository */
    private $userRepository;

    /** @var ApiTokenRepository $tokenRepository */
    private $tokenRepository;

    /** @var BraintreeService $braintreeService */
    private $braintreeService;

    /** @var \Twig_Environment $templating */
    private $templating;

    /** @var Swift_Mailer $mailer */
    private $mailer;

    /** @var ClientRepository $clientRepository */
    private $clientRepository;

    /**
     * AuthService constructor.
     * @param ContainerInterface     $container
     * @param EntityManagerInterface $em
     * @param FormFactoryInterface   $formFactory
     * @param ApiTokenRepository     $apiTokenRepository
     * @param BraintreeService       $braintreeService
     * @param Swift_Mailer $mailer
     */
    public function __construct(
        ContainerInterface $container,
        EntityManagerInterface $em,
        FormFactoryInterface $formFactory,
        ApiTokenRepository $apiTokenRepository,
        BraintreeService $braintreeService,
        Swift_Mailer $mailer
    )
    {
        $this->em = $em;
        $this->container = $container;
        $this->verificationRepository = $this->em->getRepository('UserApiBundle:VerificationCode');
        $this->formFactory = $formFactory;
        $this->encoder = $this->container->get('security.password_encoder');
        $this->responseService = $this->container->get('core.response_service');
        $this->userRepository = $this->em->getRepository(User::class);
        $this->tokenRepository = $apiTokenRepository;
        $this->awsSesService = $this->container->get('user_api.ses.service');
        $this->mailgunService = $this->container->get('mailgun.mail_service');
        $this->braintreeService = $braintreeService;
        $this->mailer = $mailer;
        $this->templating = $this->container->get('twig');
        $this->clientRepository = $this->em->getRepository(Client::class);
    }

    /**
     * @param Request $request
     * @return User
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function signUp(Request $request): User
    {
        $user = new User();
        $form = $this->formFactory->create(UserType::class, $user);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            throw new BadRequestHttpException('Wrong Request');
        }

        if ($form->isValid()) {
            $verificationCode = $this->createVerificationCode($user->getEmail());

            $password = $this->encoder->encodePassword($user, $user->getPassword());
            $user->setPassword($password);

            $this->em->persist($user);
            $this->em->flush();

            $this->awsSesService->sendEmail(
                $verificationCode->getEmail(),
                $this->templating->render("emails/verification_code.html.twig", ['code' => $verificationCode->getCode()]),
                'Your Verification Code'
            );

            return $user;
        }

        throw new FormValidationException($this->responseService->getFormError($form));
    }

    /**
     * @param Request $request
     * @param string $scope
     * @param string|null $clientId
     * @param string|null $secret
     * @return ApiToken|null
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     * @throws \Exception
     */
    public function loginV2(Request $request, string $scope, ?string $clientId = null, ?string $secret = null): ?ApiToken
    {
        /** @var User $user */
        $user = $this->userRepository->findOneBy([ 'email' => $request->get('email') ]);

        if (is_null($user)) {
            throw new NotFoundHttpException('User not found');
        }

        if (!$this->encoder->isPasswordValid($user, $request->get('password'))) {
            throw new BadRequestHttpException('Invalid password');
        }

        if (!$user->getIsVerified()) {

            /** @var VerificationCode $oldCode */
            $oldCode = $this->verificationRepository->findOneBy([
                'email' => $user->getEmail(),
                'status' => VerificationCodeStatus::PENDING
            ]);

            if ($oldCode)
            {
                if ($oldCode->getExpiredAt() > new \DateTime()) {
                    throw new UnauthorizedHttpException('', 'Please confirm your account with verification code');
                }

                $oldCode->setStatus(VerificationCodeStatus::REJECTED);
                $oldCode->setExpiredAt(new \DateTime());
                $this->em->persist($oldCode);
                $this->em->flush();
            }

            $verificationCode = $this->createVerificationCode($user->getEmail());
            $this->awsSesService->sendEmail(
                $verificationCode->getEmail(),
                $this->templating->render('emails/verification_code.html.twig', ['code' => $verificationCode->getCode()]),
                'Your Verification Code'
            );

            return null;
        }

        if ($clientId && $secret) {
            /** @var Client|null $client */
            $client = $this->clientRepository->findClientByPublicId($clientId);

            if (is_null($client)) {
                throw new BadRequestHttpException('Client not found');
            }

            if (!$client->checkSecret($secret)) {
                throw new BadRequestHttpException('Secret is wrong');
            }

            /** @var ApiToken|null $token */
            $token = $this->tokenRepository->getActualTokenByUserScopeClient($user, $scope, $client);

            if (is_null($token)) {
                throw new BadRequestHttpException('Scope or client is wrong');
            }

            if ($token->isExpired()) {
                $token = $this->createToken($user, $client, $scope);
            }
        } else {
            $client = new Client();
            $this->em->persist($client);

            $token = $this->createToken($user, $client, $scope);
            $user->addApiToken($token);

            $this->em->persist($user);
            $this->em->flush();
        }

        /** @var Subscription|bool $subscription */
        $subscription = $user->getLastSubscription();

        /** Creating customer in braintree. Added for ability fill balance on android, if subscription iOS */
        if ($scope === ApiToken::SCOPE_ANDROID &&
            /*($subscription &&
            ($subscription->getProviderType() === null || $subscription->getProviderType() === Subscription::PROVIDER_APPLE_IN_APP)) &&*/
            is_null($user->getCustomerId()))
        {
            $customerId = $this->braintreeService->createCustomer($user);

            if (is_null($customerId)) {
                throw new BadRequestHttpException('Corrupt customer data');
            }

            $user->setCustomerId($customerId);
            $this->em->persist($user);
            $this->em->flush();
        }

        return $token;
    }

    /**
     * @param Request $request
     * @param string $scope
     * @return ApiToken|null
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function verifyV2(Request $request, string $scope): ?ApiToken
    {
        /** @var User $user */
        $user = $this->userRepository->findOneBy(['email' => $request->get('email')]);
        if (is_null($user)) {
            throw new BadRequestHttpException('Invalid user');
        }

        if ($user->getIsVerified()) {
            throw new BadRequestHttpException('User already verified');
        }

        /** @var VerificationCode $code */
        $code = $this->verificationRepository->findActualByCodeAndEmail(
            $request->get('verificationNumber'),
            $request->get('email')
        );

        if (is_null($code)) {
            throw new BadRequestHttpException('Invalid verification number');
        }

        if ($code->getExpiredAt() <= new \DateTime()) {
            $code->setStatus(VerificationCodeStatus::EXPIRED);

            $this->em->persist($code);
            $this->em->flush();

            return null;
        }

        if ($scope === ApiToken::SCOPE_ANDROID) {
            $customerId = $this->braintreeService->createCustomer($user);

            if (is_null($customerId)) {
                throw new BadRequestHttpException('Corrupt customer data');
            }

            $user->setCustomerId($customerId);
        }

        $code->setStatus(VerificationCodeStatus::ACCEPTED);
        $code->setUsed(true);
        $code->setUsedAt(new \DateTime());
        $this->em->persist($code);

        $user->setIsVerified(true);

        $client = new Client();
        $this->em->persist($client);

        $token = $this->createToken($user, $client, $scope);
        $user->addApiToken($token);

        $this->em->persist($user);
        $this->em->flush();

        return $token;
    }

    /**
     * @param Request $request
     * @return ApiToken|null
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function login(Request $request) : ?ApiToken
    {
        /** @var User $user */
        $user = $this->userRepository->findOneBy([ 'email' => $request->get('email') ]);
        if (is_null($user)) {
            throw new NotFoundHttpException('User not found');
        }

        if (!$this->encoder->isPasswordValid($user, $request->get('password'))) {
            throw new BadRequestHttpException('Invalid password');
        }

        if (!$user->getIsVerified()) {
            /** @var VerificationCode $oldCode */
            $oldCode = $this->verificationRepository->findOneBy([
                'email' => $user->getEmail(),
                'status' => VerificationCodeStatus::PENDING
            ]);

            if ($oldCode) {
                if ($oldCode->getExpiredAt() > new \DateTime()) {
                    throw new UnauthorizedHttpException('', 'Please confirm your account with verification code');
                } else {
                    $oldCode->setStatus(VerificationCodeStatus::REJECTED);
                    $oldCode->setExpiredAt(new \DateTime());
                    $this->em->persist($oldCode);
                    $this->em->flush();
                }
            }

            $verificationCode = $this->createVerificationCode($user->getEmail());
            $this->awsSesService->sendEmail(
                $verificationCode->getEmail(),
                $this->templating->render('emails/verification_code.html.twig', ['code' => $verificationCode->getCode()]),
                'Your Verification Code'
            );
            return null;
        }

        /** @var ApiToken|null $token */
        $token = $this->getCurrentToken($user);
        
        if (is_null($token) || $token->getExpireAt() <= new \DateTime()) {
            $token = $this->createToken($user);
        }

        return $token;
    }

    /**
     * @param Request $request
     * @return ApiToken|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function verify(Request $request) : ?ApiToken
    {
        /** @var User $user */
        $user = $this->userRepository->findOneBy(['email' => $request->get('email')]);
        if (is_null($user)) {
            throw new BadRequestHttpException('Invalid user');
        }

        /** @var VerificationCode $code */
        $code = $this->verificationRepository->findActualByCodeAndEmail(
            $request->get('verificationNumber'),
            $request->get('email')
        );

        if (is_null($code)) {
            throw new BadRequestHttpException('Invalid verification number');
        }

        if ($code->getExpiredAt() <= new \DateTime()) {
            $code->setStatus(VerificationCodeStatus::EXPIRED);

            $this->em->persist($code);
            $this->em->flush();

            return null;
        }

        $customerId = $this->braintreeService->createCustomer($user);
        if (is_null($customerId)) {
            throw new BadRequestHttpException('Corrupt customer data');
        }

        $user->setCustomerId($customerId);

        $code->setStatus(VerificationCodeStatus::ACCEPTED);
        $code->setUsed(true);
        $code->setUsedAt(new \DateTime());
        $this->em->persist($code);

        $user->setIsVerified(true);

        $token = $this->createToken($user);
        $user->addApiToken($token);

        $this->em->persist($user);
        $this->em->flush();

        return $token;
    }

    /**
     * @param $email
     * @param $codeType
     * @return VerificationCode
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function createVerificationCode($email, $codeType = VerificationCodeType::REGISTRATION) : VerificationCode
    {
        $verificationCode = new VerificationCode();
        $verificationCode->setCode(str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT));
        $verificationCode->setEmail($email);
        $verificationCode->setUsed(false);
        $verificationCode->setSentAt(new \DateTime());
        $verificationCode->setStatus(VerificationCodeStatus::PENDING);
        $verificationCode->setType($codeType);

        $this->em->persist($verificationCode);
        $this->em->flush();

        return $verificationCode;
    }

    /**
     * @param User $user
     * @param Client|null $client
     * @param string|null $scope
     * @return ApiToken
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function createToken(User $user, ?Client $client = null, ?string $scope = null) : ApiToken
    {
        $token = new ApiToken();
        $token->setToken(md5(uniqid().time()));
        $token->setExpireAt((new \DateTime())->modify('+1 year'));
        $token->setUser($user);
        $token->setScope($scope);
        $token->setClient($client);

        $this->em->persist($token);
        $this->em->flush();

        return $token;
    }

    /**
     * @param Request $request
     * @param User $user
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateUser(Request $request, User $user): void
    {
        $form = $this->formFactory->create(UserUpdateType::class, $user, ['method' => 'patch']);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            throw new BadRequestHttpException('Wrong Request');
        }

        if (!$form->isValid()) {
            throw new FormValidationException($this->responseService->getFormError($form));
        }

        $this->em->persist($user);
        $this->em->flush();
    }

    /**
     * @param Request $request
     * @param User $user
     * @return User|null
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function changePassword(Request $request, User $user): ?User
    {
        $changePasswordModel = new ChangePassword();
        $form = $this->formFactory->create(PasswordChangeType::class, $changePasswordModel);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            throw new BadRequestHttpException('Wrong Request');
        }

        if ($form->isValid()) {
            if($changePasswordModel->getNewPassword() === $changePasswordModel->getOldPassword())
                throw new FormValidationException('New password should be different from the current password');
            $password = $this->encoder->encodePassword($user, $changePasswordModel->getNewPassword());
            $user->setPassword($password);

            $this->em->persist($user);
            $this->em->flush();

            return $user;
        }

        throw new FormValidationException($this->responseService->getFormError($form));
    }

    /**
     * @param string $email
     *
     * @return VerificationCode $verificationCode
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function forgotPassword ($email) {
        /** @var User $user */
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (is_null($user)) {
            throw new BadRequestHttpException('Invalid user');
        }

        $verificationCode = $this->createVerificationCode($email, VerificationCodeType::RESET_PASSWORD);

        $this->awsSesService->sendEmail(
            $verificationCode->getEmail(),
            $this->templating->render('emails/verification_restore_password.html.twig', ['code' => $verificationCode->getCode()]),
            'Your reset password code');

        return $verificationCode;
    }

    /**
     * @param Request $request
     * @return VerificationCode
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function verifyForgot(Request $request) : VerificationCode
    {
        /** @var User $user */
        $user = $this->userRepository->findOneBy(['email' => $request->request->get('email')]);
        if (is_null($user)) {
            throw new BadRequestHttpException('Invalid user');
        }

        /** @var VerificationCode $code */
        $code = $this->verificationRepository->findActualByCodeAndEmail(
            $request->request->get('verificationNumber'),
            $request->request->get('email'),
            VerificationCodeType::RESET_PASSWORD,
            [VerificationCodeStatus::ACCEPTED, VerificationCodeStatus::PENDING]
        );

        if (is_null($code)) {
            throw new BadRequestHttpException('Invalid verification number');
        }

        if ($code->getExpiredAt() <= new \DateTime()) {
            $code->setStatus(VerificationCodeStatus::EXPIRED);

            $this->em->persist($code);
            $this->em->flush();

            return null;
        }

        $code->setStatus(VerificationCodeStatus::ACCEPTED);

        $this->em->persist($code);

        $this->em->flush();

        return $code;
    }

    /**
     * @param Request $request
     * @return User|null
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function changeForgot(Request $request): ?User
    {
        /** @var User $user */
        $user = $this->userRepository->findOneBy(['email' => $request->request->get('email')]);
        if (is_null($user)) {
            throw new BadRequestHttpException('Invalid user');
        }

        /** @var VerificationCode $code */
        $code = $this->verificationRepository->findActualByCodeAndEmail(
            $request->request->get('verificationNumber'),
            $request->request->get('email'),
            VerificationCodeType::RESET_PASSWORD,
            VerificationCodeStatus::ACCEPTED
        );

        if (is_null($code)) {
            throw new BadRequestHttpException('Invalid verification number');
        }

        if ($code->getExpiredAt() <= new \DateTime()) {
            $code->setStatus(VerificationCodeStatus::EXPIRED);

            $this->em->persist($code);
            $this->em->flush();

            return null;
        }

        $changeForgotPasswordModel = new ChangeForgotPassword();
        $form = $this->formFactory->create(ForgotPasswordType::class, $changeForgotPasswordModel);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            throw new BadRequestHttpException('Wrong Request');
        }

        if ($form->isValid()) {

            if($this->encoder->isPasswordValid($user, $changeForgotPasswordModel->getNewPassword()))
                throw new BadRequestHttpException("You can't use your previous password as new one");

            $code->setUsed(true);
            $code->setUsedAt(new \DateTime());
            $this->em->persist($code);

            $password = $this->encoder->encodePassword($user, $changeForgotPasswordModel->getNewPassword());
            $user->setPassword($password);

            $this->em->persist($user);
            $this->em->flush();

            return $user;
        }

        throw new FormValidationException($this->responseService->getFormError($form));

    }

    /**
     * @param Request $request
     * @return User|null
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function resendCode(Request $request): ?User
    {
        $email = $request->request->get('email');

        /** @var User $user */
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (is_null($user)) {
            throw new BadRequestHttpException('Invalid user');
        }

        if($user->getIsVerified()) {
            throw new BadRequestHttpException('User already verified');
        }

        /** @var VerificationCode $code */
        $code = $this->verificationRepository->findOneBy([
            'email' => $email,
            'used' => false,
            'type' => VerificationCodeType::REGISTRATION,
            'status' => VerificationCodeStatus::PENDING,
        ]);

        if (is_null($code)) {
            $code = $this->createVerificationCode($email);
        }

        if ($code->getExpiredAt() <= new \DateTime()) {
            $code->setStatus(VerificationCodeStatus::EXPIRED);

            $this->em->persist($code);

            $code = $this->createVerificationCode($email);
        }

        $this->awsSesService->sendEmail(
            $code->getEmail(),
            $this->templating->render('emails/verification_code.html.twig', ['code' => $code->getCode()]),
            'Your Verification Code'
        );

        return $user;
    }

    /**
     * @param Request $request
     *
     * @throws ORMException
     */
    public function logout(Request $request) : void
    {
        /** @var ApiToken $token  */
        $token = $this->tokenRepository->findOneBy(['token' => $request->headers->get('X-API-Token')]);

        $this->em->remove($token);
        $this->em->flush();
    }

    /**
     * @param User $user
     * @param string|null $scope
     * @param string|null $client
     * @return ApiToken|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getCurrentToken(User $user, ?string $scope = null, ?string $client = null): ?ApiToken
    {
        if (!is_null($client)) {
            /** @var Client|null $client */
            $client = $this->clientRepository->findClientByPublicId($client);
        }

        /** @var ApiToken|null $token */
        $token = $this->tokenRepository->getActualTokenByUserScopeClient($user, $scope, $client);
        return $token;
    }
}

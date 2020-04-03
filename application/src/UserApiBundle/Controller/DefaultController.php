<?php

namespace UserApiBundle\Controller;

use CoreBundle\{
    Exception\FormValidationException,
    Security\Voter\BaseVoter,
    Services\ResponseService
};
use FOS\RestBundle\Controller\{
    AbstractFOSRestController,
    Annotations as Rest
};
use FOS\RestBundle\View\View;
use SubscriptionBundle\Entity\Subscription;
use Symfony\Component\HttpFoundation\Request;
use Swagger\Annotations as SWG;
use UserApiBundle\{
    Security\Voter\UserVoter,
    Services\DeviceService
};
use Symfony\Component\{
    HttpFoundation\Response,
    HttpKernel\Exception\UnauthorizedHttpException,
    Security\Core\Exception\AccessDeniedException
};
use UserApiBundle\Entity\{ApiToken, Balance, User};
use UserApiBundle\Services\{
    AuthService,
    BalanceService
};

/**
 * Class DefaultController
 * @package UserApiBundle\Controller
 */
class DefaultController extends AbstractFOSRestController
{
    /** @var BalanceService $balanceService */
    private $balanceService;

    /** @var AuthService $authService */
    private $authService;

    /** @var DeviceService $deviceService */
    private $deviceService;

    /** @var ResponseService $responseService */
    private $responseService;

    /**
     * DefaultController constructor.
     * @param AuthService    $authService
     * @param BalanceService $balanceService
     * @param DeviceService $deviceService
     * @param ResponseService $responseService
     */
    public function __construct(
        AuthService $authService,
        BalanceService $balanceService,
        DeviceService $deviceService,
        ResponseService $responseService
    )
    {
        $this->authService = $authService;
        $this->balanceService = $balanceService;
        $this->deviceService = $deviceService;
        $this->responseService = $responseService;
    }

    /**
     * @Rest\Post("signup", name="api_user_signup")
     * @Rest\View()
     *
     * @param Request $request
     * @return View
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Twig\Error\Error
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     * @SWG\Post(
     *     summary="registers new user",
     *     description="create new user & send verification code",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     * @SWG\Response(
     *     response=201,
     *     description="Returns the success message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "Registration successful, check your email for verification code"}
     *     )
     * ),
     * @SWG\Response(
     *     response=422,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "property invalid massage"}
     *     )
     * ),
     * @SWG\Response(
     *     response=400,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example=
     *          { "message": "Wrong request" }
     *     )
     * ),
     * @SWG\Parameter(
     *     name="email",
     *     in="formData",
     *     type="string",
     *     required=true,
     * )
     * @SWG\Parameter(
     *     name="password",
     *     in="formData",
     *     type="string",
     *     required=true,
     * )
     * @SWG\Parameter(
     *     name="firstName",
     *     in="formData",
     *     type="string",
     *     required=false,
     * )
     * @SWG\Parameter(
     *     name="lastName",
     *     in="formData",
     *     type="string",
     *     required=false,
     * )
     * @SWG\Parameter(
     *     name="phoneNumber",
     *     in="formData",
     *     type="string",
     *     required=false,
     * )
     */
    public function signUpAction(Request $request)
    {
        try {
            $this->authService->signUp($request);
            return $this->view(
                ['message' => 'Registration successful, check your email for verification code'],
                Response::HTTP_CREATED
            );
        } catch (FormValidationException $exception) {
            return $this->view(
                ['message' => $exception->getError()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (\RuntimeException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * @Rest\Post("login", name="api_user_login")
     * @Rest\View(serializerGroups={"auth"})
     *
     * @param Request $request
     * @return ApiToken|View
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     * @SWG\Post(
     *     summary="user authentification",
     *     description="authentificate user",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns user object",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *           "token": "9b5f9bc49ab2d7d5accbea7ddb2f834d",
     *           "subscription_expired_at": null,
     *           "subscription_status": null,
     *           "is_autorenew": false,
     *           "is_subscription_trial": null,
     *           "balance": null,
     *           "price_per_recognition": 2,
     *           "id": 42,
     *           "email": "ilya.ryakhin+11@umbrellait.com",
     *           "last_name": null,
     *           "first_name": null,
     *           "phone_number": null,
     *           "website": null,
     *           "is_trial_used": false
     *        }
     *     )
     * ),
     * @SWG\Response(
     *     response=401,
     *     description="When user not verified, checking verification code. If code expired - resend new code. Else - just showing message for verification",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *          { "message": "Mail with verification code send to your email" } ,
     *          { "message": "Please confirm your account with verification code" }
     *        }
     *     )
     * ),
     * @SWG\Response(
     *     response=400,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *          { "message": "Invalid password" },
     *          { "message": "User not found" }
     *        }
     *     )
     * ),
     * @SWG\Parameter(
     *     name="email",
     *     in="formData",
     *     type="string",
     *     required=true,
     * )
     * @SWG\Parameter(
     *     name="password",
     *     in="formData",
     *     type="string",
     *     required=true,
     * )
     */
    public function loginAction(Request $request)
    {
        try {
            $token = $this->authService->login($request);

            if (is_null($token)) {
                return $this->view(
                    ['message' => 'Mail with verification code send to your email'],
                    Response::HTTP_UNAUTHORIZED
                );
            }

            return $token;
        } catch (UnauthorizedHttpException $exception){
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_UNAUTHORIZED
            );
        } catch (\RuntimeException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * @Rest\Post("verify", name="api_user_verify")
     * @Rest\View(serializerGroups={"auth"})
     *
     * @param Request $request
     * @return ApiToken|View
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     *
     * @SWG\Post(
     *     summary="cheking user verification code",
     *     description="cheking user verification code",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns the success message & verify user",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *           "token": "9b5f9bc49ab2d7d5accbea7ddb2f834d",
     *           "subscription_expired_at": null,
     *           "subscription_status": null,
     *           "is_autorenew": false,
     *           "is_subscription_trial": null,
     *           "balance": null,
     *           "price_per_recognition": 2,
     *           "id": 42,
     *           "email": "ilya.ryakhin+11@umbrellait.com",
     *           "last_name": null,
     *           "first_name": null,
     *           "phone_number": null,
     *           "website": null,
     *           "is_trial_used": false
     *        }
     *     )
     * ),
     * @SWG\Response(
     *     response=400,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *          { "message": "Invalid verification number" },
     *          { "message": "Invalid user"}
     *        }
     *     )
     * ),
     * @SWG\Response(
     *     response=422,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "Verification number expired"}
     *     )
     * ),
     * @SWG\Parameter(
     *     name="verificationNumber",
     *     in="formData",
     *     type="string",
     *     required=true,
     * )
     * @SWG\Parameter(
     *     name="email",
     *     in="formData",
     *     type="string",
     *     required=true,
     * )
     */
    public function verifyAction(Request $request)
    {
        try {
            $token = $this->authService->verify($request);

            if (is_null($token)) {
                return $this->view(
                    ['message' => 'Verification number expired'],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            return $token;
        } catch (\RuntimeException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * @Rest\Put("change-password", name="api_user_change_password")
     * @Rest\View()
     *
     * @param Request $request
     * @return View
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     *
     * @SWG\Put(
     *     summary="change user password",
     *     description="change user password",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns success message",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *           "message": "Password has been successfully changed"
     * }
     *     )
     * ),
     * @SWG\Response(
     *     response=400,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *           "message": "Error message"
     *        }
     *     )
     * ),
     * @SWG\Response(
     *     response=401,
     *     description="Access denied",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *          "message": "Access denied"
     *        }
     *     )
     * ),
     * @SWG\Response(
     *     response=422,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "property error message"}
     *     )
     * ),
     * @SWG\Parameter(
     *     name="oldPassword",
     *     in="formData",
     *     type="string",
     *     required=true,
     * )
     * @SWG\Parameter(
     *     name="newPassword",
     *     in="formData",
     *     type="string",
     *     required=true,
     * )
     */
    public function changePasswordAction(Request $request) {
        try {
            $user = $this->getUser();

            $this->denyAccessUnlessGranted(UserVoter::ACCESS_CHANGE_PASSWORD, $user);

            $this->authService->changePassword($request, $user);

            return $this->view(
                ['message' => 'Password changed successfully'],
                Response::HTTP_OK
            );
        } catch (FormValidationException $exception) {
            return $this->view(
                ['message' => $exception->getError()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (AccessDeniedException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_UNAUTHORIZED
            );
        } catch (\RuntimeException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * @Rest\Get("me", name="api_user_me")
     * @Rest\View()
     *
     * @param Request $request
     * @return Response|View
     *
     * @SWG\Get(
     *     summary="get authorized user profile",
     *     description="get authorized user profile",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns the  user",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *           "token": "500eda5eefe2474c23e92f2820204482",
     *           "subscription_expired_at": null,
     *           "subscription_status": null,
     *           "is_autorenew": false,
     *           "is_subscription_trial": null,
     *           "balance": null,
     *           "price_per_recognition": 2,
     *           "client_id": "8_5d275974454618.70508118",
     *           "id": 36,
     *           "email": "ilya.ryakhin+5@umbrellait.com",
     *           "last_name": null,
     *           "first_name": null,
     *           "phone_number": null,
     *           "website": null,
     *           "is_trial_used": false
     *       }
     *     )
     * ),
     * @SWG\Response(
     *     response=401,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *          "message": "Access denied"
     *        }
     *     )
     * )
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function meAction(Request $request) {
        try {
            /** @var User $user */
            $user = $this->getUser();
            $this->denyAccessUnlessGranted(BaseVoter::ACCESS_VIEW, $user);
            
            $serializeGroup = $request->headers->has('scope') 
                ? 'auth.'.$request->headers->get('scope') 
                : 'auth';

            return new Response(
                $this->responseService->serialize(
                    $this->authService->getCurrentToken(
                        $user,
                        $request->headers->get('scope'),
                        $request->headers->get('client')
                    ),
                    [$serializeGroup]
                ),
                Response::HTTP_OK
            );
        } catch (AccessDeniedException $e){
            return $this->view(
                ['message' => 'Access denied'],
                Response::HTTP_UNAUTHORIZED
            );
        } catch (\RuntimeException $e){
            return $this->view(
                ['message' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * @Rest\Patch("me", name="api_user_update_info")
     * @Rest\View()
     *
     * @param Request $request
     * @return Response|View
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     *
     * @SWG\Patch(
     *     summary="updates logged in user profile info",
     *     description="update user profile info",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns updated user object",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *           "token": "500eda5eefe2474c23e92f2820204482",
     *           "subscription_expired_at": null,
     *           "subscription_status": null,
     *           "is_autorenew": false,
     *           "is_subscription_trial": null,
     *           "balance": null,
     *           "price_per_recognition": 2,
     *           "client_id": "8_5d275974454618.70508118",
     *           "id": 36,
     *           "email": "ilya.ryakhin+5@umbrellait.com",
     *           "last_name": null,
     *           "first_name": null,
     *           "phone_number": null,
     *           "website": null,
     *           "is_trial_used": false
     * }
     *     )
     * ),
     * @SWG\Response(
     *     response=422,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"property": "property invalid massage"}
     *     )
     * ),
     * @SWG\Response(
     *     response=400,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example=
     *          { "message": "Wrong request" }
     *     )
     * ),
     * @SWG\Response(
     *     response=401,
     *     description="Access denied",
     *     @SWG\Schema(
     *        type="object",
     *        example=
     *          { "message": "Access deniedt" }
     *     )
     * ),
     *
     * @SWG\Parameter(
     *     name="firstName",
     *     in="formData",
     *     type="string",
     *     required=false,
     * )
     * @SWG\Parameter(
     *     name="lastName",
     *     in="formData",
     *     type="string",
     *     required=false,
     * )
     * @SWG\Parameter(
     *     name="website",
     *     in="formData",
     *     type="string",
     *     required=false,
     * )
     * @SWG\Parameter(
     *     name="phoneNumber",
     *     in="formData",
     *     type="string",
     *     required=false,
     * )
     */
    public function updateAction (Request $request) {
        try {
            $user = $this->getUser();
            $this->denyAccessUnlessGranted(BaseVoter::ACCESS_EDIT, $user);

            $this->authService->updateUser($request, $user);

            $serializeGroup = $request->headers->has('scope') 
                ? 'auth.'.$request->headers->get('scope') 
                : 'auth';

            return new Response(
                $this->responseService->serialize(
                    $this->authService->getCurrentToken(
                        $user,
                        $request->headers->get('scope'),
                        $request->headers->get('client')
                    ),
                    [$serializeGroup]
                ),
                Response::HTTP_OK
            );
        } catch (FormValidationException $exception) {
            return $this->view(
                ['message' => $exception->getError()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (AccessDeniedException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_UNAUTHORIZED
            );
        } catch (\RuntimeException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * @Rest\Post("logout", name="api_user_logout")
     * @Rest\View()
     *
     * @param Request $request
     * @throws \Doctrine\ORM\ORMException
     *
     * @return User|View
     *
     * @SWG\Post(
     *     summary="logout user",
     *     description="logout user",
     *     consumes={"application/x-www-form-urlencoded"},
     *     @SWG\Parameter(
     *          name="device_token",
     *          in="formData",
     *          type="string",
     *          required=false,
     *          description="To delete device"
     *      )
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns success message",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *           "message": "Successfully logged out"
     * }
     *     )
     * ),
     * @SWG\Response(
     *     response=401,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *          "message": "Access denied"
     *        }
     *     )
     * )
     */
    public function logoutAction(Request $request) {
        try {
            $this->denyAccessUnlessGranted(BaseVoter::ACCESS_VIEW, $this->getUser());

            $deviceToken = $request->get('device_token', null);
            if ($deviceToken) {
                $this->deviceService->deleteByToken($this->getUser(), $deviceToken);
            }
            $this->authService->logout($request);

            return  $this->view(
                ['message' => 'Successfully logged out'],
                Response::HTTP_OK
            );
        } catch (AccessDeniedException $e){
            return $this->view(
                ['message' => 'Access denied'],
                Response::HTTP_UNAUTHORIZED
            );
        } catch (\RuntimeException $e){
            return $this->view(
                ['message' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * @Rest\Post("forgot", name="api_user_forgot")
     * @Rest\View()
     *
     * @param Request $request
     * @return User|View
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     * @SWG\Post(
     *     summary="Create user's reset password verification code",
     *     description="Create user's reset password verification code",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns the success message",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *           "message": "Check your email for reset password code",
     * }
     *     )
     * ),
     * @SWG\Response(
     *     response=400,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={ "message": "Invalid user"}
     *     )
     * ),
     * @SWG\Parameter(
     *     name="email",
     *     in="formData",
     *     type="string",
     *     required=true,
     * )
     */
    public function forgotAction(Request $request)
    {
        try {
            $this->authService->forgotPassword($request->request->get('email'));

            return $this->view(
                ['message' => 'Check your email for reset password code'],
                Response::HTTP_CREATED
            );
        } catch (\RuntimeException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }


    /**
     * @Rest\Post("verify-forgot", name="api_user_verify_forgot")
     * @Rest\View()
     *
     * @param Request $request
     * @return User|View
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     *
     * @SWG\Post(
     *     summary="checks user verification code for reset password",
     *     description="checks user verification code for reset password",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns the success message ",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *           "message": "Verification code is ok",
     * }
     *     )
     * ),
     * @SWG\Response(
     *     response=400,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={ "message": "Invalid verification number" }
     *     )
     * ),
     * @SWG\Response(
     *     response=422,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "Verification number expired"}
     *     )
     * ),
     * @SWG\Parameter(
     *     name="verificationNumber",
     *     in="formData",
     *     type="string",
     *     required=true,
     * )
     * @SWG\Parameter(
     *     name="email",
     *     in="formData",
     *     type="string",
     *     required=true,
     * )
     */
    public function verifyForgotAction(Request $request) {
        try {
            $user = $this->authService->verifyForgot($request);

            if (is_null($user)) {
                return $this->view(
                    ['message' => 'Verification number expired'],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            return $this->view(
                ['message' => 'Verification code is ok'],
                Response::HTTP_OK
            );
        } catch (\RuntimeException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * @Rest\Put("change-forgot", name="api_user_change_forgot")
     * @Rest\View()
     *
     * @param Request $request
     * @return User|View
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     *
     * @SWG\Put(
     *     summary="Reset user password to new one",
     *     description="Reset user password to new one",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns the success message ",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *           "message": "Password successfully changed"
     * }
     *     )
     * ),
     * @SWG\Response(
     *     response=400,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example=
     *          { "message": "Invalid verification number" }
     *     )
     * ),
     * @SWG\Response(
     *     response=422,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "Verification number expired"}
     *     )
     * ),
     * @SWG\Parameter(
     *     name="newPassword",
     *     in="formData",
     *     type="string",
     *     required=true,
     * )
     * @SWG\Parameter(
     *     name="verificationNumber",
     *     in="formData",
     *     type="string",
     *     required=true,
     * )
     * @SWG\Parameter(
     *     name="email",
     *     in="formData",
     *     type="string",
     *     required=true,
     * )
     */
    public function changeForgotAction(Request $request) {
        try {
            $user = $this->authService->changeForgot($request);

            if (is_null($user)) {
                return $this->view(
                    ['message' => 'Verification number expired'],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            return $this->view(
                ['message' => 'Password successfully changed'],
                Response::HTTP_OK
            );
        } catch (FormValidationException $exception) {
            return $this->view(
                ['message' => $exception->getError()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (\RuntimeException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * @Rest\Post("resend-code", name="api_user_resend_code")
     * @Rest\View()
     *
     * @param Request $request
     *
     * @return User|View
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     * @SWG\Post(
     *     summary="Resend verification code",
     *     description="Resend verification code",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns the success message ",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *           "message": "Verification code sent to your email address",
     * }
     *     )
     * ),
     * @SWG\Response(
     *     response=400,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example=
     *          { "message": "Something wrong happened" }
     *     )
     * ),
     * @SWG\Parameter(
     *     name="email",
     *     in="formData",
     *     type="string",
     *     required=true,
     * )
     */
    public function resendCodeAction(Request $request) {
        try {
            $this->authService->resendCode($request);

            return $this->view(
                ['message' => 'Verification code sent to your email address'],
                Response::HTTP_OK
            );

        } catch (\RuntimeException $e){
            return $this->view(
                ['message' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * @Rest\Post("balance", name="api_user_balance_fill")
     * @Rest\View(serializerGroups={"balance"})
     *
     * @param Request $request
     * @return View|Balance
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Money\UnknownCurrencyException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     *
     * @SWG\Post(
     *     summary="Balance filling",
     *     description="Balance filling",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     *
     * @SWG\Response(
     *     response=200,
     *     description="Returns the success message",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *           "balance_amount": "-$12.00",
     *           "montly_limit_amount": "$65.5",
     *           "is_charge_limit_enabled": false,
     *           "is_limit_warning_enabled": false
     *           }
     *     )
     * ),
     * @SWG\Response(
     *     response=400,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *          {"message": "Payment method nonce is invalid."},
     *
     *        }
     *     )
     * ),
     * @SWG\Response(
     *     response=401,
     *     description="Access denied",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "Access denied"}
     *     )
     * ),
     * @SWG\Response(
     *     response=422,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "property invalid massage"}
     *     )
     * )
     *
     * @SWG\Parameter(
     *     name="payment_method_nonce",
     *     in="formData",
     *     type="string",
     *     required=true,
     * )
     *
     * @SWG\Parameter(
     *     name="balance_amount",
     *     in="formData",
     *     type="integer",
     *     required=true,
     * )
     */
    public function fillBalanceAction(Request $request)
    {
        try {
            /** @var User $user */
            $user = $this->getUser();

            $this->denyAccessUnlessGranted(UserVoter::ACCESS_FILL_BALANCE, $user);

            /** @var Subscription $subscription */
            $subscription = $user->getActiveSubscription();

            if (is_null($subscription)) {
                return $this->view(
                    ['message' => 'Please purchase subscription to refill balance'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            if ($subscription->getPackage()->isTrial()) {
                return $this->view(
                    ['message' => 'Please subscribe to a paid plan to refill balance'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            return $this->balanceService->fillBalance($request, $user);
        } catch (FormValidationException $exception) {
            return $this->view(
                ['message' => $exception->getError()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (AccessDeniedException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_UNAUTHORIZED
            );
        } catch (\RuntimeException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * @Rest\Post("limit", name="api_user_limit_fill")
     * @Rest\View(serializerGroups={"balance"})
     *
     * @param Request $request
     * @return View|Balance
     * @throws \Doctrine\ORM\ORMException
     * @throws \Money\UnknownCurrencyException
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @SWG\Post(
     *     summary="Balance limit filling",
     *     description="Balance limit filling",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     *
     * @SWG\Response(
     *     response=200,
     *     description="Returns the success message",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *           "balance_amount": "-$12.00",
     *           "montly_limit_amount": "$65.5",
     *           "is_charge_limit_enabled": false,
     *           "is_limit_warning_enabled": false
     *           }
     *     )
     * ),
     * @SWG\Response(
     *     response=400,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *          {"message": "Bad request"},
     *
     *        }
     *     )
     * ),
     * @SWG\Response(
     *     response=401,
     *     description="Access denied",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "Access denied"}
     *     )
     * ),
     * @SWG\Response(
     *     response=422,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "property invalid massage"}
     *     )
     * )
     *
     * @SWG\Parameter(
     *     name="charge_limit_enabled",
     *     in="formData",
     *     type="boolean",
     *     required=true,
     *     default="true",
     * )
     *
     * @SWG\Parameter(
     *     name="monthly_limit",
     *     in="formData",
     *     type="number"
     * )
     *
     * @SWG\Parameter(
     *     name="warn_limit_reached",
     *     in="formData",
     *     type="boolean"
     * )
     */
    public function fillBalanceLimitAction(Request $request)
    {
        try {
            /** @var User $user */
            $user = $this->getUser();

            $this->denyAccessUnlessGranted(UserVoter::ACCESS_FILL_BALANCE, $user);

            return $this->balanceService->fillBalanceLimit($request, $user);
        } catch (FormValidationException $exception) {
            return $this->view(
                ['message' => $exception->getError()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (AccessDeniedException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_UNAUTHORIZED
            );
        } catch (\RuntimeException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * @Rest\Get("purchase_history", name="api_user_purchase_history")
     * @Rest\View(serializerGroups={"purchase_history"})
     *
     * @param Request $request
     * @return View|array
     *
     * @Rest\QueryParam(
     *     name="page",
     *     requirements="\d+"
     * )
     * @Rest\QueryParam(
     *     name="limit",
     *     requirements="\d+"
     * )
     *
     * @SWG\Get(
     *     summary="Get list of user's transactions",
     *     description="Get list of user's transactions",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns list of user's transactions",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *          "count": 12,
     *           "pageCount": 3,
     *           "transactions":
     *                  {
     *                      "balance_amount": "$0.25",
     *                      "date": "2019-04-11T14:21:09+00:00",
     *                      "type": 0
     *                  },
     *                  {
     *                      "balance_amount": "$0.50",
     *                      "date": "2019-04-11T15:50:02+00:00",
     *                      "type": 0
     *                  },
     *                  {
     *                      "balance_amount": "$0.50",
     *                      "date": "2019-04-11T16:03:02+00:00",
     *                      "type": 1
     *                  },
     *                  {
     *                      "balance_amount": "$50.00",
     *                      "date": "2019-04-15T08:43:59+00:00",
     *                      "type": 1
     *                  },
     *                  {
     *                      "balance_amount": "$50.00",
     *                      "date": "2019-04-15T08:44:08+00:00",
     *                      "type": 0
     *                  }
     *          }
     *     )
     * ),
     * @SWG\Response(
     *     response=401,
     *     description="Access denied error",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "Access Denied"}
     *     )
     * ),
     * @SWG\Response(
     *     response=400,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "Error message"}
     *     )
     * )
     * @throws \Doctrine\ORM\Query\QueryException
     */
    public function getPurchaseHistoryAction(Request $request)
    {
        try {
            /** @var User $user */
            $user = $this->getUser();

            $this->denyAccessUnlessGranted(BaseVoter::ACCESS_VIEW, $user);

            $filters = $request->query->all();

            $page = $this->extractParam($filters, 'page', 1);
            $perPage = $this->extractParam($filters, 'limit', 10);

            if($page < 1 || $perPage < 1) {
                return $this->view(['message' => 'Wrong Query Params'],Response::HTTP_BAD_REQUEST);
            }

            return $this->balanceService->purchaseHistory($user, $page, $perPage);
        } catch (AccessDeniedException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_UNAUTHORIZED
            );
        } catch (\RuntimeException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * @param array  $array
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    protected function extractParam(array &$array, $key, $default)
    {
        if (isset($array[$key])) {
            $result = $array[$key];
            unset($array[$key]);
        } else {
            $result = $default;
        }

        return $result;
    }
}

<?php

namespace UserApiBundle\Controller\V2;

use CoreBundle\Exception\FormValidationException;
use CoreBundle\Services\ResponseService;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\View\View;
use SubscriptionBundle\Entity\Subscription;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use UserApiBundle\Entity\ApiToken;
use UserApiBundle\Entity\Balance;
use UserApiBundle\Entity\User;
use UserApiBundle\Security\Voter\UserVoter;
use UserApiBundle\Services\AuthService;
use UserApiBundle\Services\BalanceService;
use UserApiBundle\Services\DeviceService;
use FOS\RestBundle\Controller\Annotations as Rest;
use Swagger\Annotations as SWG;

/**
 * Class UserController
 * @package UserApiBundle\Controller\V2
 */
class UserController extends AbstractFOSRestController
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
     * @param AuthService     $authService
     * @param BalanceService  $balanceService
     * @param DeviceService   $deviceService
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
     * @Rest\Post("login", name="api_v2_user_login")
     * @Rest\View()
     *
     * @param Request $request
     * @return Response|View
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     *
     * @SWG\Post(
     *     summary="user authentification",
     *     description="authentificate user",
     *     consumes={"application/x-www-form-urlencoded"},
     *     tags={"V2"},
     * )
     *
     * @SWG\Response(
     *     response=200,
     *     description="Returns user object",
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
     *           "secret": "5d275974454765.86750284",
     *           "id": 36,
     *           "email": "ilya.ryakhin+5@umbrellait.com",
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
     * @SWG\Parameter(
     *     name="client",
     *     in="formData",
     *     type="string",
     *     required=false,
     * )
     * @SWG\Parameter(
     *     name="secret",
     *     in="formData",
     *     type="string",
     *     required=false,
     * )
     * @SWG\Parameter(
     *     name="scope",
     *     in="header",
     *     required=true, 
     *     type="string",
     *     default="ios", 
     *     description="scope"  
     * )
     */
    public function loginAction(Request $request)
    {
        try {
            $scope = $request->headers->get('scope');
            $clientId = $request->get('client');
            $secret = $request->get('secret');

            if (is_null($scope) || !in_array($scope, [ApiToken::SCOPE_ANDROID, ApiToken::SCOPE_IOS])) {
                return $this->view(['message' => 'Scope not supported'],Response::HTTP_BAD_REQUEST);
            }

            /** @var ApiToken $token */
            $token = $this->authService->loginV2($request, $scope, $clientId, $secret);

            if (is_null($token)) {
                return $this->view(
                    ['message' => 'Mail with verification code send to your email'],
                    Response::HTTP_UNAUTHORIZED
                );
            }

            $groups = ['auth.'.$scope];

            if (is_null($clientId) && is_null($secret)) {
                $groups = array_merge($groups, ['verify.'.$scope]);
            }

            return new Response($this->responseService->serialize($token, $groups),Response::HTTP_OK);
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
     * @Rest\Post("verify", name="api_v2_user_verify")
     * @Rest\View()
     *
     * @param Request $request
     * @return Response|View
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     *
     * @SWG\Post(
     *     summary="cheking user verification code",
     *     description="cheking user verification code",
     *     consumes={"application/x-www-form-urlencoded"},
     *     tags={"V2"},
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns the success message & verify user",
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
     *           "secret": "5d275974454765.86750284",
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
     * @SWG\Parameter(
     *     name="scope",
     *     in="header",
     *     required=true, 
     *     type="string",
     *     default="ios", 
     *     description="scope"  
     * )
     */
    public function verifyAction(Request $request)
    {
        try {
            $scope = $request->headers->get('scope');

            if (is_null($scope) || !in_array($scope, [ApiToken::SCOPE_ANDROID, ApiToken::SCOPE_IOS])) {
                return $this->view(['message' => 'Scope not supported'],Response::HTTP_BAD_REQUEST);
            }

            /** @var ApiToken $token */
            $token = $this->authService->verifyV2($request, $scope);

            if (is_null($token)) {
                return $this->view(
                    ['message' => 'Verification number expired'],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            return new Response($this->responseService->serialize($token, ['auth.'.$scope, 'verify.'.$scope]), Response::HTTP_OK);
        } catch (\RuntimeException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * @Rest\Get("balance_fill_allowed", name="api_v2_user_balance_fill_check")
     * @Rest\View()
     *
     * @param Request $request
     * @return View
     * @throws \Exception
     *
     * @SWG\Get(
     *     summary="Balance filling check",
     *     description="Balance filling check",
     *     consumes={"application/x-www-form-urlencoded"},
     *     tags={"V2"}
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns the success message",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *          {
     *              "message": "success",
     *              "isBalanceRefillAllowed": true
     *          },
     *          {
     *              "message": "Please purchase subscription to refill balance",
     *              "isBalanceRefillAllowed": false
     *          },
     *          {
     *              "message": "Please subscribe to a paid plan to refill balance",
     *              "isBalanceRefillAllowed": false
     *          }
     *     }
     *     )
     * ),
     *  @SWG\Response(
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
     * )
     */
    public function verifyBalanceRefill(Request $request)
    {
        try {
            /** @var User $user */
            $user = $this->getUser();

            $this->denyAccessUnlessGranted(UserVoter::ACCESS_FILL_BALANCE, $user);

            $response = ['message' => 'success', 'isBalanceRefillAllowed' => true];

            /** @var Subscription $subscription */
            $subscription = $user->getActiveSubscription();

            if (is_null($subscription)) {
                $response = [
                    'message' => 'Please purchase subscription to refill balance',
                    'isBalanceRefillAllowed' => false
                ];
            }

            if (!is_null($subscription) && $subscription->getPackage()->isTrial()) {
                $response = [
                    'message' => 'Please subscribe to a paid plan to refill balance',
                    'isBalanceRefillAllowed' => false
                ];
            }

            return $this->view($response,Response::HTTP_OK);
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
     * @Rest\Post("balance", name="api_v2_user_balance_fill")
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
     *     tags={"V2"}
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
     * 
     * @SWG\Parameter(
     *     name="scope",
     *     in="header",
     *     required=true, 
     *     type="string",
     *     default="ios", 
     *     description="scope"  
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
}

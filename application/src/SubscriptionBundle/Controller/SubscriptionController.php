<?php

namespace SubscriptionBundle\Controller;

use CoreBundle\Exception\FormValidationException;
use CoreBundle\Security\Voter\BaseVoter;
use SubscriptionBundle\Entity\Subscription;
use SubscriptionBundle\Security\Voter\SubscriptionVoter;
use SubscriptionBundle\Services\BraintreeService;
use FOS\RestBundle\Controller\{ AbstractFOSRestController, Annotations as Rest };
use FOS\RestBundle\View\View;
use Swagger\Annotations as SWG;
use SubscriptionBundle\Services\SubscriptionService;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use UserApiBundle\Entity\Balance;
use UserApiBundle\Entity\User;

/**
 * Class SubscriptionController
 * @package ExperienceBundle\Controller
 */
class SubscriptionController extends AbstractFOSRestController
{
    /** @var BraintreeService $braintreeService */
    private $braintreeService;

    /** @var SubscriptionService $subscriptionService */
    private $subscriptionService;

    /**
     * SubscriptionController constructor.
     * @param BraintreeService    $braintreeService
     * @param SubscriptionService $subscriptionService
     */
    public function __construct(
        BraintreeService $braintreeService,
        SubscriptionService $subscriptionService
    )
    {
        $this->braintreeService = $braintreeService;
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * @Rest\Get("/token", name="api_get_payment_token")
     *
     * @return View
     *
     * @SWG\Get(
     *     summary="Generate client token",
     *     description="Generate client token & create customer in braintree vault",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     *
     * @SWG\Response(
     *     response=200,
     *     description="Returns the success message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"token": "eyJ2ZXJzaW9uIjoyLCJhdXRob3JpemF0aW9uRmluZ2VycHJpbnQiOiI1YjQ5N2MxZTI5NzJlZTJlOTVhZTIyOGMwNDU4MDM4NGMwMmU4NjAwYTNhYTE1ZmM1ZjU4MzFhYTg3ZjkwMzJhfGNyZWF0ZWRfYXQ9MjAxOS0wMy0wNlQyMzowNjo0OC44MjYzMDI1ODkrMDAwMFx1MDAyNmN1c3RvbWVyX2lkPTY2MDc0MTE4NFx1MDAyNm1lcmNoYW50X2lkPXFzenRjZjQ2OG41MzVmeXJcdTAwMjZwdWJsaWNfa2V5PXg0NWI5YnpweHp5MjdnYmsiLCJjb25maWdVcmwiOiJodHRwczovL2FwaS5zYW5kYm94LmJyYWludHJlZWdhdGV3YXkuY29tOjQ0My9tZXJjaGFudHMvcXN6dGNmNDY4bjUzNWZ5ci9jbGllbnRfYXBpL3YxL2NvbmZpZ3VyYXRpb24iLCJncmFwaFFMIjp7InVybCI6Imh0dHBzOi8vcGF5bWVudHMuc2FuZGJveC5icmFpbnRyZWUtYXBpLmNvbS9ncmFwaHFsIiwiZGF0ZSI6IjIwMTgtMDUtMDgifSwiY2hhbGxlbmdlcyI6W10sImVudmlyb25tZW50Ijoic2FuZGJveCIsImNsaWVudEFwaVVybCI6Imh0dHBzOi8vYXBpLnNhbmRib3guYnJhaW50cmVlZ2F0ZXdheS5jb206NDQzL21lcmNoYW50cy9xc3p0Y2Y0NjhuNTM1ZnlyL2NsaWVudF9hcGkiLCJhc3NldHNVcmwiOiJodHRwczovL2Fzc2V0cy5icmFpbnRyZWVnYXRld2F5LmNvbSIsImF1dGhVcmwiOiJodHRwczovL2F1dGgudmVubW8uc2FuZGJveC5icmFpbnRyZWVnYXRld2F5LmNvbSIsImFuYWx5dGljcyI6eyJ1cmwiOiJodHRwczovL29yaWdpbi1hbmFseXRpY3Mtc2FuZC5zYW5kYm94LmJyYWludHJlZS1hcGkuY29tL3FzenRjZjQ2OG41MzVmeXIifSwidGhyZWVEU2VjdXJlRW5hYmxlZCI6dHJ1ZSwicGF5cGFsRW5hYmxlZCI6dHJ1ZSwicGF5cGFsIjp7ImRpc3BsYXlOYW1lIjoiY2FwdHVtIiwiY2xpZW50SWQiOm51bGwsInByaXZhY3lVcmwiOiJodHRwOi8vZXhhbXBsZS5jb20vcHAiLCJ1c2VyQWdyZWVtZW50VXJsIjoiaHR0cDovL2V4YW1wbGUuY29tL3RvcyIsImJhc2VVcmwiOiJodHRwczovL2Fzc2V0cy5icmFpbnRyZWVnYXRld2F5LmNvbSIsImFzc2V0c1VybCI6Imh0dHBzOi8vY2hlY2tvdXQucGF5cGFsLmNvbSIsImRpcmVjdEJhc2VVcmwiOm51bGwsImFsbG93SHR0cCI6dHJ1ZSwiZW52aXJvbm1lbnROb05ldHdvcmsiOnRydWUsImVudmlyb25tZW50Ijoib2ZmbGluZSIsInVudmV0dGVkTWVyY2hhbnQiOmZhbHNlLCJicmFpbnRyZWVDbGllbnRJZCI6Im1hc3RlcmNsaWVudDMiLCJiaWxsaW5nQWdyZWVtZW50c0VuYWJsZWQiOnRydWUsIm1lcmNoYW50QWNjb3VudElkIjoiY2FwdHVtIiwiY3VycmVuY3lJc29Db2RlIjoiVVNEIn0sIm1lcmNoYW50SWQiOiJxc3p0Y2Y0NjhuNTM1ZnlyIiwidmVubW8iOiJvZmYifQ=="}
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
     *     response=400,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *          {"message": "Wrong request"},
     *        }
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
     */
    public function generateClientTokenAction(): View
    {
        //TODO: add check by scope
        $this->denyAccessUnlessGranted(BaseVoter::ACCESS_CREATE, new Subscription());

        try {
            $token = $this->subscriptionService->createToken($this->getUser());

            return $this->view(['token' => $token],Response::HTTP_OK);
        } catch (\RuntimeException $exception) {
            return $this->view(['message' => $exception->getMessage()],Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @Rest\Post("/assign", name="api_assign_subscription")
     * @Rest\View(serializerGroups={"subscription_current"})
     *
     * @param Request $request
     * @return object|View
     * @throws \Exception
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @SWG\Post(
     *     summary="Assign new subscription",
     *     description="Assign new subscription with creating new payment method",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     *
     * @SWG\Response(
     *     response=200,
     *     description="Returns the success message",
     *     @SWG\Schema(
     *        type="object",
     *        example={ "active_experiences_count": 1,
     *                  "available_experiences_count": 0,
     *                  "package": {
     *                      "price": "$20.00",
     *                      "id": 2,
     *                      "title": "1 exp for $20",
     *                      "description": "1 exp for $20",
     *                      "expires_in_months": 1,
     *                      "experiences_number": 1,
     *                      "icon": null
     *                  },
     *                  "expires_at": "2019-04-11T07:09:51+02:00",
     *                  "status": 1,
     *                  "is_autorenew": true
     *     }
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
     * )
     *
     * @SWG\Parameter(
     *     name="package",
     *     in="formData",
     *     type="integer",
     *     required=true,
     * )
     */
    public function assignSubscriptionAction(Request $request)
    {
        try {
            $this->denyAccessUnlessGranted(BaseVoter::ACCESS_CREATE, new Subscription());

            /** @var User $user */
            $user = $this->getUser();

            /** @var Subscription $currentSubscription */
            $currentSubscription = $user->getActiveSubscription();

            if ($currentSubscription && !$currentSubscription->getPackage()->isTrial()) {
                return $this->view(
                    ['message' => 'You already have active subscription!'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            return $this->subscriptionService->assignSubscription($request, $user);
        } catch (AccessDeniedException $exception) {
            return $this->view(['message' => $exception->getMessage()], Response::HTTP_UNAUTHORIZED);
        } catch (FormValidationException $exception) {
            return $this->view(['message' => $exception->getError()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $exception) {
            return $this->view(['message' => $exception->getMessage()],Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @Rest\Get("/package/list", name="api_list_packages")
     * @Rest\View(serializerGroups={"packages_list"})
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
     * @param Request $request
     * @return array|View
     * @throws \Exception
     *
     * @SWG\Get(
     *     summary="Get list of packages",
     *     description="Get list of packages",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     *
     * @SWG\Response(
     *     response=200,
     *     description="Returns list of packages",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *        "count": 5,
     *        "pageCount": 1,
     *        "packages": {
     *  {
     *   "price": "$0.00",
     *   "title": "Free trial",
     *   "id": 1,
     *   "description": "Free 1 day trial",
     *   "expires_in_months": 0,
     *   "experiences_number": 1,
     *    "is_trial": false,
     *    "icon": null
     *   },
     *   {
     *   "price": "$20.00",
     *   "title": "1 exp for $20",
     *   "id": 2,
     *   "description": "1 exp for $20",
     *   "expires_in_months": 1,
     *   "experiences_number": 1,
     *    "is_trial": false,
     *    "icon": null
     *   },
     *  {
     *   "price": "$40.00",
     *   "title": "3 exp for $40",
     *   "id": 3,
     *   "description": "3 exp for $40",
     *   "expires_in_months": 1,
     *   "experiences_number": 3,
     *   "is_trial": false,
     *   "icon": null
     *   },
     *   {
     *   "price": "$60.00",
     *   "title": "5 exp for $60",
     *   "id": 4,
     *   "description": "5 exp for $60",
     *   "expires_in_months": 1,
     *   "experiences_number": 5,
     *   "is_trial": false,
     *   "icon": null
     *   },
     *   {
     *   "price": "$100.00",
     *   "title": "10 exp for $100",
     *   "id": 5,
     *   "description": "10 exp for $100",
     *   "expires_in_months": 1,
     *   "experiences_number": 10,
     *   "is_trial": false,
     *   "icon": null
     *   }
     * }
     *     }
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
     * ),
     */
    public function listPackagesAction(Request $request)
    {
        try {
            $this->denyAccessUnlessGranted(BaseVoter::ACCESS_LIST, new Subscription());

            $filters = $request->query->all();

            $page = $this->extractParam($filters, 'page', 1);
            $perPage = $this->extractParam($filters, 'limit', 10);

            if($page < 1 || $perPage < 1) {
                return $this->view(['message' => 'Wrong Query Params'],Response::HTTP_BAD_REQUEST);
            }

            return $this->subscriptionService->listPackages($this->getUser(), $page, $perPage);
        } catch (AccessDeniedException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_UNAUTHORIZED
            );
        } catch (BadRequestHttpException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }  catch (\RuntimeException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * @Rest\Get("/current", name="api_subscription_current")
     * @Rest\View(serializerGroups={"subscription_current"})
     *
     * @return array|View
     * @throws \Exception
     *
     * @SWG\Get(
     *     summary="Get user current subscription",
     *     description="Get user current subscription",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     *
     * @SWG\Response(
     *     response=200,
     *     description="Returns the success message",
     *     @SWG\Schema(
     *        type="object",
     *        example= {
     *     "subscription": {
     *          "active_experiences_count": 1,
     *          "available_experiences_count": 0,
     *          "package": {
     *              "price": "$20.00",
     *              "id": 2,
     *              "title": "1 exp for $20",
     *              "description": "1 exp for $20",
     *              "expires_in_months": 1,
     *              "experiences_number": 1,
     *              "is_trial": false,
     *              "icon": null
     *            },
     *          "expires_at": "2019-04-18T10:32:18+00:00",
     *          "status": 3,
     *          "is_autorenew": true
     *          }
     *      }
     *     )
     * ),
     *
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
     *        example={"message": "You have no active subscriptions"}
     *     )
     * ),
     *
     */
    public function currentSubscriptionAction()
    {
        try {
            $this->denyAccessUnlessGranted(BaseVoter::ACCESS_VIEW, new Subscription());

            $result = $this->getUser()->getLastSubscription();

            return $this->view(['subscription' => $result ? $result : null]);
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
     * @Rest\Post("/change", name="api_subscription_change")
     * @Rest\View(serializerGroups={"subscription_current"})
     *
     * @param Request $request
     * @return object|View
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @SWG\Post(
     *     summary="Change subscription",
     *     description="Change subscription with creating new payment method",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     *
     * @SWG\Response(
     *     response=200,
     *     description="Returns the success message",
     *     @SWG\Schema(
     *        type="object",
     *        example={ "active_experiences_count": 1,
     *                  "available_experiences_count": 0,
     *                  "package": {
     *                      "price": "$20.00",
     *                      "id": 2,
     *                      "title": "1 exp for $20",
     *                      "description": "1 exp for $20",
     *                      "expires_in_months": 1,
     *                      "experiences_number": 1,
     *                      "is_trial": false,
     *                      "icon": null
     *                  },
     *                  "expires_at": "2019-04-11T07:09:51+02:00",
     *                  "status": 1,
     *                  "is_autorenew": true
     *     }
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
     *     name="package",
     *     in="formData",
     *     type="integer",
     *     required=true,
     * )
     */
    public function changeSubscription(Request $request)
    {
        try {
            /** @var User $user */
            $user = $this->getUser();

            /** @var Subscription $currentSubscription */
            $currentSubscription = $user->getActiveSubscription();

            if (!$currentSubscription) {
                return $this->view(
                    ['message' => 'You have no active subscriptions'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $this->denyAccessUnlessGranted(BaseVoter::ACCESS_EDIT, $currentSubscription);

            return $this->subscriptionService->changeSubscription($request, $currentSubscription, $user);
        } catch (AccessDeniedException $exception) {
            return $this->view(['message' => $exception->getMessage()], Response::HTTP_UNAUTHORIZED);
        } catch (FormValidationException $exception) {
            return $this->view(['message' => $exception->getError()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $exception) {
            return $this->view(['message' => $exception->getMessage()],Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @Rest\Post("/cancel", name="api_subscription_cancel")
     * @Rest\View(serializerGroups={"subscription_current"})
     *
     * @param Request $request
     * @return View|object
     *
     * @throws \Exception
     *
     *      * @SWG\Post(
     *     summary="Stop subscription",
     *     description="Stop subscription",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     *
     * @SWG\Response(
     *     response=200,
     *     description="Returns the success message",
     *     @SWG\Schema(
     *        type="object",
     *        example={ "active_experiences_count": 1,
     *                  "available_experiences_count": 0,
     *                  "package": {
     *                      "price": "$20.00",
     *                      "id": 2,
     *                      "title": "1 exp for $20",
     *                      "description": "1 exp for $20",
     *                      "expires_in_months": 1,
     *                      "experiences_number": 1,
     *                      "is_trial": false,
     *                      "icon": null
     *                  },
     *                  "expires_at": "2019-04-11T07:09:51+02:00",
     *                  "status": 1,
     *                  "is_autorenew": false
     *     }
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
     */
    public function cancelAction(Request $request)
    {
        try {
            $this->denyAccessUnlessGranted(BaseVoter::ACCESS_CANCEL, new Subscription());

            /** @var Subscription $currentSubscription */
            $currentSubscription = $this->getUser()->getActiveSubscription();

            if (!$currentSubscription) {
                return $this->view(
                    ['message' => 'You have no active subscriptions'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            if ($currentSubscription->getPackage()->isTrial()) {
                return $this->view(
                    ['message' => 'You can cancel a trial subscription by purchasing a new subscription.'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$currentSubscription->isAutorenew() && !$currentSubscription->getPackage()->isTrial()) {
                return $this->view(
                    ['message' => 'Your current subscription already canceled.'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            return $this->subscriptionService->cancelSubscription($currentSubscription);
        } catch (AccessDeniedException $exception) {
            return $this->view(['message' => $exception->getMessage()],Response::HTTP_UNAUTHORIZED);
        } catch (\RuntimeException $exception) {
            return $this->view(['message' => $exception->getMessage()],Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @Rest\Post("/payment/update", name="api_subscription_payment_update")
     * @Rest\View(serializerGroups={"subscription_current"})
     *
     * @param Request $request
     * @return View|Subscription
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Money\UnknownCurrencyException
     *
     * @SWG\Post(
     *     summary="Update payment method for current subscriprtion",
     *     description="Update payment method for current subscriprtion",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     *
     * @SWG\Response(
     *     response=200,
     *     description="Returns the success message",
     *     @SWG\Schema(
     *        type="object",
     *        example={ "active_experiences_count": 1,
     *                  "available_experiences_count": 0,
     *                  "package": {
     *                      "price": "$20.00",
     *                      "id": 2,
     *                      "title": "1 exp for $20",
     *                      "description": "1 exp for $20",
     *                      "expires_in_months": 1,
     *                      "experiences_number": 1,
     *                      "is_trial": false,
     *                      "icon": null
     *                  },
     *                  "expires_at": "2019-04-11T07:09:51+02:00",
     *                  "status": 1,
     *                  "is_autorenew": true
     *     }
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
     *
     * @SWG\Parameter(
     *     name="payment_method_nonce",
     *     in="formData",
     *     type="string",
     *     required=true,
     * )
     */
    public function updatePaymentMethod(Request $request)
    {
        try {
            $this->denyAccessUnlessGranted(SubscriptionVoter::ACCESS_PAYMENT_UPDATE, new Subscription());

            /** @var Subscription $currentSubscription */
            $currentSubscription = $this->getUser()->getActiveSubscriptionUpdatePaymentMethod();

            if (!$currentSubscription) {
                return $this->view(
                    ['message' => 'You have no active subscriptions'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            if ($currentSubscription->getPackage()->isTrial()) {
                return $this->view(
                    ['message' => 'You cann\'t update payment method for trial plan.'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$request->request->has('payment_method_nonce')) {
                return $this->view(
                    ['message' => 'Payment method required.'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            return $this->subscriptionService->updatePaymentMethod(
                $currentSubscription,
                $request->request->get('payment_method_nonce'));

        } catch (AccessDeniedException $exception) {
            return $this->view(['message' => $exception->getMessage()], Response::HTTP_UNAUTHORIZED);
        } catch (\RuntimeException $exception) {
            return $this->view(['message' => $exception->getMessage()],Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @Rest\Post("/listener", name="api_subscription_listener")
     *
     * @param Request $request
     * @return View
     *
     * @throws \Braintree\Exception\InvalidSignature
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function braintreeSubscriptionListener(Request $request)
    {
        try {
            $notification = $this->subscriptionService->processNotification($request);

            if ($notification) {
                return $this->view($notification,Response::HTTP_OK);
            }

            return $this->view($notification,Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * @Rest\Get("/current-extended", name="api_subscription_current_extended")
     * @Rest\View(serializerGroups={"subscription_current"})
     *
     * @return View
     * @throws \Exception
     *
     * @SWG\Get(
     *     summary="Get user current subscription",
     *     description="Get user current subscription",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     *
     * @SWG\Response(
     *     response=200,
     *     description="Returns the success message",
     *     @SWG\Schema(
     *        type="object",
     *        example= {
     *              "subscription": {
     *                  "active_experiences_count": 11,
     *                  "available_experiences_count": -6,
     *                  "package": {
     *                      "price": "$60.00",
     *                      "id": 4,
     *                      "title": "5 active experiences",
     *                      "description": "5 exp for $60",
     *                      "expires_in_months": 1,
     *                      "experiences_number": 5,
     *                      "is_trial": false,
     *                      "icon": null,
     *                      "recognitions_number": 1000
     *                  },
     *                  "expires_at": "2019-04-27T11:52:45+00:00",
     *                  "status": 1,
     *                  "is_autorenew": true
     *              },
     *              "balance": {
     *                   "balance_amount": "$1,010.00",
     *                   "montly_limit_amount": "$100.0",
     *                   "is_charge_limit_enabled": true,
     *                   "is_limit_warning_enabled": false
     *              },
     *              "views": {
     *                  "total": 6000,
     *                  "free": 600,
     *                  "paid": 5000
     *              }
     *          }
     *     )
     * ),
     *
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
     *        example={"message": "You have no active subscriptions"}
     *     )
     * ),
     */
    public function extendedCurrentSubscriptionAction()
    {
        try {
            $this->denyAccessUnlessGranted(BaseVoter::ACCESS_VIEW, new Subscription());

            /** @var User $user */
            $user = $this->getUser();

            /** @var Subscription $subscription */
            $subscription = $user->getLastSubscription();

            /** @var Balance $balance */
            $balance = $user->getBalance();

            $free = $user->getViewsLeftFreeNumber($subscription && $subscription->isActive() ? $subscription : null);

            $total = $subscription
                ? $subscription->getPackage()->getRecognitionsNumber() + $user->getTotalAvailableViews($subscription)
                : 0;

            $views = [
                'total' => $total,
                'free' => $free <= 0 ? 0 : $free,
                'paid' => $user->getViewsLeftPaidNumber($subscription ? $subscription : null),
            ];

            $data = [
                'subscription' => $subscription ? $subscription : null,
                'balance' => $balance,
                'views' => $views,
            ];

            if (!is_null($subscription) && $subscription && $subscription->getProviderType() === Subscription::PROVIDER_APPLE_IN_APP &&
                $subscription->getAppleDowngradeEnabled())
            {
                $data['downgrade'] = [
                    'newPlan' => $subscription->getNextPlan(),
                    'changeDate' => $subscription->getExpiresAt()
                ];
            }

            return $this->view($data);
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

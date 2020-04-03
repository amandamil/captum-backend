<?php

namespace SubscriptionBundle\Controller\V2;

use CoreBundle\Exception\FormValidationException;
use CoreBundle\Security\Voter\BaseVoter;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\View\View;
use SubscriptionBundle\Entity\Subscription;
use SubscriptionBundle\Services\SubscriptionService;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use UserApiBundle\Entity\User;
use FOS\RestBundle\Controller\Annotations as Rest;
use Swagger\Annotations as SWG;

/**
 * Class SubscriptionController
 * @package SubscriptionBundle\Controller\V2
 */
class SubscriptionController extends AbstractFOSRestController
{
    /** @var SubscriptionService $subscriptionService */
    private $subscriptionService;

    /**
     * SubscriptionController constructor.
     * @param SubscriptionService $subscriptionService
     */
    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * @Rest\Post("/assign", name="api_v2_assign_subscription")
     * @Rest\View(serializerGroups={"subscription_current"})
     *
     * @param Request $request
     * @return View|Subscription
     *
     * @SWG\Post(
     *     summary="Assign new in-app subscription",
     *     description="Assign new in-app subscription",
     *     consumes={"application/x-www-form-urlencoded"},
     *     tags={"V2"},
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns the success message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"subscription": {
     *                      "active_experiences_count": 2,
     *                      "available_experiences_count": 3,
     *                      "package": {
     *                              "price": "$60.00",
     *                              "id": 4,
     *                              "title": "5 active experiences",
     *                              "description": "5 exp for $60",
     *                              "expires_in_months": 1,
     *                              "experiences_number": 5,
     *                              "is_trial": false,
     *                              "icon": null,
     *                              "recognitions_number": 1000,
     *                              "apple_product_id": "43ncOMNzZg"
     *                      },
     *                      "expires_at": "2019-07-27T11:52:45+00:00",
     *                      "status": 1,
     *                      "is_autorenew": true,
     *                      "provider_type": "braintree"
     *                  },
     *                  "balance": null,
     *                  "views": {
     *                      "total": 1000,
     *                      "free": 1000,
     *                      "paid": 0
     *                  }
     *                 }
     *     )
     * )
     *
     * @SWG\Response(
     *     response=400,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *              "message": "Payment receipt is invalid."
     *        }
     *     )
     * )
     *
     * @SWG\Response(
     *     response=401,
     *     description="Access denied",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "Access denied"}
     *     )
     * )
     *
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
     * @SWG\Parameter(
     *     name="scope",
     *     in="header",
     *     required=true, 
     *     type="string",
     *     default="ios", 
     *     description="scope"  
     * )
     * @SWG\Parameter(
     *     name="client",
     *     in="header",
     *     required=true, 
     *     type="string"
     * )
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function assignInAppSubscriptionAction(Request $request)
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
     * @Rest\Post("/change", name="api_v2_subscription_change")
     * @Rest\View(serializerGroups={"subscription_current"})
     *
     * @param Request $request
     * @return object|View
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @SWG\Post(
     *     summary="Change subscription",
     *     description="Change subscription",
     *     consumes={"application/x-www-form-urlencoded"},
     *     tags={"V2"},
     * )
     *
     * @SWG\Response(
     *     response=200,
     *     description="Returns the success message",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *                 "subscription": {
     *                      "active_experiences_count": 2,
     *                      "available_experiences_count": 3,
     *                      "package": {
     *                              "price": "$60.00",
     *                              "id": 4,
     *                              "title": "5 active experiences",
     *                              "description": "5 exp for $60",
     *                              "expires_in_months": 1,
     *                              "experiences_number": 5,
     *                              "is_trial": false,
     *                              "icon": null,
     *                              "recognitions_number": 1000,
     *                              "apple_product_id": "43ncOMNzZg"
     *                      },
     *                      "expires_at": "2019-07-27T11:52:45+00:00",
     *                      "status": 1,
     *                      "is_autorenew": true,
     *                      "provider_type": "braintree"
     *                  },
     *                  "balance": null,
     *                  "views": {
     *                      "total": 1000,
     *                      "free": 1000,
     *                      "paid": 0
     *                  },
     *                  "downgrade": {
     *                      "newPlan": {
     *                          "price": "$20.00",
     *                          "id": 2,
     *                          "title": "1 active experience",
     *                          "description": "1 exp for $20",
     *                          "expires_in_months": 1,
     *                          "experiences_number": 1,
     *                          "is_trial": false,
     *                          "icon": null,
     *                          "recognitions_number": 1000,
     *                          "apple_product_id": "T30iMCs4jG"
     *                      },
     *                      "changeDate": "2019-10-15T07:23:29+00:00"
     *                  }
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
     * @SWG\Parameter(
     *     name="scope",
     *     in="header",
     *     required=true, 
     *     type="string",
     *     default="ios", 
     *     description="scope"  
     * )
     * @SWG\Parameter(
     *     name="client",
     *     in="header",
     *     required=true, 
     *     type="string"
     * )
     */
    public function changeInAppSubscriptionAction(Request $request)
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

            $currentSubscription = $this->subscriptionService->changeSubscription($request, $currentSubscription, $user);

            if ($currentSubscription->getProviderType() === Subscription::PROVIDER_APPLE_IN_APP &&
                $currentSubscription->getAppleDowngradeEnabled())
            {
                $data = [
                    'subscription' => $currentSubscription,
                    'downgrade' => [
                        'newPlan' => $currentSubscription->getNextPlan(),
                        'changeDate' => $currentSubscription->getExpiresAt()
                    ]
                ];

                return $this->view($data);
            }

            return $currentSubscription;
        } catch (AccessDeniedException $exception) {
            return $this->view(['message' => $exception->getMessage()], Response::HTTP_UNAUTHORIZED);
        } catch (FormValidationException $exception) {
            return $this->view(['message' => $exception->getError()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $exception) {
            return $this->view(['message' => $exception->getMessage()],Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @Rest\Post("/listener_in_app", name="api_v2_subscription_apple_listener")
     * @SWG\Post(
     *     summary="Subscription webhook listener",
     *     description="Subscription webhook listener",
     *     consumes={"application/x-www-form-urlencoded"},
     *     tags={"V2"},
     * )
     *
     * @SWG\Response(
     *     response=200,
     *     description="Returns the success message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "success"}
     *     )
     * )
     *
     * @SWG\Response(
     *     response=400,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "failure"}
     *     )
     * )
     * @param Request $request
     * @return View
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Money\UnknownCurrencyException
     */
    public function inAppSubscriptionListener(Request $request)
    {
        try {
            $notification = $this->subscriptionService->processInAppNotification($request);

            if ($notification) {
                return $this->view(['message' => 'success'],Response::HTTP_OK);
            }

            return $this->view(['message' => 'failure'], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * @Rest\Get("/products/list", name="api_v2_list_products")
     * @Rest\View(serializerGroups={"products_list"})
     *
     *  @Rest\QueryParam(
     *     name="page",
     *     requirements="\d+"
     * )
     * @Rest\QueryParam(
     *     name="limit",
     *     requirements="\d+"
     * )
     * @SWG\Get(
     *     summary="Get list of products",
     *     description="Get list of products",
     *     consumes={"application/x-www-form-urlencoded"},
     *     tags={"V2"},
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns list of packages",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *              "count": 6,
     * "pageCount": 1,
     * "products": {
     * {
     * "price": "$20.00",
     * "id": 1,
     * "product_id": "com.umbrellait.CaptumApp.one.buck"
     * },
     * {
     * "price": "$50.00",
     * "id": 2,
     * "product_id": "com.umbrellait.CaptumApp.two.buck"
     * },
     * {
     * "price": "$100.00",
     * "id": 3,
     * "product_id": "com.umbrellait.CaptumApp.three.buck"
     * },
     * {
     * "price": "$300.00",
     * "id": 4,
     * "product_id": "com.umbrellait.CaptumApp.foure.buck"
     * },
     * {
     * "price": "$500.00",
     * "id": 5,
     * "product_id": "com.umbrellait.CaptumApp.six.buck"
     * },
     * {
     * "price": "$1,000.00",
     * "id": 6,
     * "product_id": "com.umbrellait.CaptumApp.seven.buck"
     * }
     * }
     *        }))
     *
     * @param Request $request
     * @return array|View
     * @throws \Exception
     */
    public function listProductAction(Request $request)
    {
        try {
            $filters = $request->query->all();

            $page = $this->extractParam($filters, 'page', 1);
            $perPage = $this->extractParam($filters, 'limit', 10);

            if($page < 1 || $perPage < 1) {
                return $this->view(['message' => 'Wrong Query Params'],Response::HTTP_BAD_REQUEST);
            }

            return $this->subscriptionService->listProducts($page, $perPage);
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
     * @Rest\Post("/verify-receipt", name="api_v2_subscription_apple_verify_receipt")
     *
     *  @SWG\Post(
     *     summary="Subscription verify receipt",
     *     description="verify receipt",
     *     consumes={"application/x-www-form-urlencoded"},
     *     tags={"V2"},
     * )
     * @SWG\Parameter(
     *     name="receipt",
     *     in="formData",
     *     type="string",
     *     required=true,
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns the success message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "success"}
     *     )
     * )
     * @SWG\Response(
     *     response=400,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "failure"}
     *     )
     * )
     *
     * @param Request $request
     * @return View
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Money\UnknownCurrencyException
     */
    public function validateReceiptAction(Request $request)
    {
        try {
            /** @var User $user */
            $user = $this->getUser();

            /** @var Subscription|null $currentSubscription */
            $currentSubscription = $user->getActiveSubscription();

            if (!is_null($currentSubscription) && !$currentSubscription->getPackage()->isTrial()) {
                /** @var Subscription $subscription */
                $subscription = $currentSubscription;
            } else {
                /** @var Subscription|bool $subscription */
                $subscription = $user->getLastPendingSubscription();
            }

            if ($subscription === false) {
                return $this->view(
                    ['message' => 'You have no pending subscriptions'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $this->subscriptionService->validatePaymentReceipt($subscription, $user, $request->request->get('receipt'));

            return $this->view(['message' => 'success'], Response::HTTP_OK);
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
     * @Rest\Post("/check-account-in-use", name="api_v2_subscription_apple_check_in_use")
     * @SWG\Post(
     *     summary="Check account in use",
     *     description="Check account in use",
     *     consumes={"application/x-www-form-urlencoded"},
     *     tags={"V2"},
     * )
     * @SWG\Parameter(
     *     name="receipt",
     *     in="formData",
     *     type="string",
     *     required=true,
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns the success message",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *          {
     *              "isExist": false,
     *              "isActive": false,
     *              "isNextStepAllowed": true
     *          },
     *          {
     *              "isExist": true,
     *              "isActive": true,
     *              "isNextStepAllowed": false
     *          },
     *          {
     *              "isExist": true,
     *              "isActive": false,
     *              "isNextStepAllowed": true
     *          }
     *       }
     *     )
     * )
     * @SWG\Response(
     *     response=400,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "failure"}
     *     )
     * )
     * @param Request $request
     * @return View
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function checkIsAppleAccountAlreadyInUseAction(Request $request)
    {
        try {
            return $this->view(
                $this->subscriptionService->checkIsAppleAccountAlreadyInUse($this->getUser(), $request->request->get('receipt')),
                Response::HTTP_OK
            );
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
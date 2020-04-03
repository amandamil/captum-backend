<?php

namespace TestLogicBundle\Controller;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use ExperienceBundle\Entity\Experience;
use FOS\RestBundle\{ Controller\AbstractFOSRestController, View\View, Controller\Annotations as Rest };
use Psr\Container\ContainerInterface;
use SubscriptionBundle\Entity\Subscription;
use SubscriptionBundle\Repository\SubscriptionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use TestLogicBundle\Services\TestLogicService;
use Swagger\Annotations as SWG;
use UserApiBundle\Entity\User;

/**
 * Class TestController
 * @package TestBundle\Controller
 */
class TestLogicController extends AbstractFOSRestController
{
    /** @var EntityManager */
    private $em;

    /** @var ContainerInterface $container */
    protected $container;

    /** @var TestLogicService $testLogicService */
    private $testLogicService;

    /** @var SubscriptionRepository $subscriptionRepository */
    private $subscriptionRepository;

    /**
     * TestController constructor.
     * @param EntityManagerInterface $em
     * @param ContainerInterface $container
     * @param TestLogicService $testLogicService
     */
    public function __construct(
        EntityManagerInterface $em,
        ContainerInterface $container,
        TestLogicService $testLogicService
    )
    {
        $this->em = $em;
        $this->container = $container;
        $this->testLogicService = $testLogicService;
        $this->subscriptionRepository = $this->em->getRepository(Subscription::class);
    }

    /**
     * @Rest\Get("/get_experience/{id}/{recognitions}", name="api_test_get_experience", requirements={"id": "\d+"})
     * @Rest\View(serializerGroups={"experience"})
     *
     * @param int $id
     * @param int $recognitions
     * @return Experience|View
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Money\UnknownCurrencyException
     *
     * @SWG\Get(
     *     summary="Get experience",
     *     description="Get experience",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns experience instance",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *          "video_url": "https://s3.amazonaws.com/captum-video-transcoded/tc/9a62313d0cfb89c8391d4467bb3886c3_720.mp4",
     *          "full_hd_video_url": "https://s3.amazonaws.com/captum-video-transcoded/tc/9a62313d0cfb89c8391d4467bb3886c3_1080.mp4",
     *          "image_url": "https://captum-dev.s3.amazonaws.com/420d74b7d6efec53200cf2b0a182fd7c.jpeg",
     *          "status": 2,
     *          "title": "Test result 3555",
     *          "contact_name": null,
     *          "phone": null,
     *          "email": null,
     *          "created_at": "2019-02-12T11:07:15+01:00",
     *          "updated_at": "2019-02-12T11:07:19+01:00",
     *          "website": null
     *        }
     *     )
     * ),
     */
    public function testGetExperienceAction(int $id, int $recognitions): View
    {
        try {

            if (!$this->getUser()->hasRole('ROLE_SUPER_ADMIN')) {
                return $this->view(['message' => 'Access Denied'],Response::HTTP_UNAUTHORIZED)->setFormat('json');
            }

            return $this->view($this->testLogicService->getExperience($id, $recognitions))->setFormat('json');
        } catch (NotFoundHttpException $e) {
            return $this->view(
                ['message' => 'Experience with #ID - '.$id.' doesn\'t exist'],
                Response::HTTP_NOT_FOUND
            )->setFormat('json');
        } catch (\RuntimeException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            )->setFormat('json');
        }
    }

    /**
     * @Rest\Post("/braintree_response/{id}", name="api_subscription_test_start_autorenew", requirements={"id": "\d+"})
     * @Rest\View(serializerGroups={"subscription_current"})
     *
     * @param int $id
     * @param Request $request
     * @return View|Subscription
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Money\UnknownCurrencyException
     *
     * @SWG\Response(
     *     response=200,
     *     description="Returns the success message",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *          "active_experiences_count": 1,
     *          "available_experiences_count": 0,
     *                  "package": {
     *                      "price": "$20.00",
     *                      "id": 2,
     *                      "title": "1 exp for $20",
     *                      "description": "1 exp for $20",
     *                      "expires_in_months": 1,
     *                      "experiences_number": 1,
     *                      "icon": null
     *                  },
     *          "expires_at": "2019-04-11T07:09:51+02:00",
     *          "status": 1,
     *          "is_autorenew": true
     *     })
     * ),
     *
     * @SWG\Parameter(
     *     name="kind",
     *     in="formData",
     *     type="string",
     *     required=true,
     * )
     * @SWG\Parameter(
     *     name="current_billing_cycle",
     *     in="formData",
     *     type="integer",
     *     required=false,
     * )
     *
     */
    public function testBraintreeSubscriptionResponse(int $id, Request $request): View
    {
        try {

            if (!$this->getUser()->hasRole('ROLE_SUPER_ADMIN')) {
                return $this->view(['message' => 'Access Denied'],Response::HTTP_UNAUTHORIZED)->setFormat('json');
            }

            /** @var Subscription $subscription */
            $subscription = $this->subscriptionRepository->find($id);

            if (is_null($subscription)) {
                throw new NotFoundHttpException();
            }

            $this->testLogicService->testAutorenew(
                $subscription,
                $request->request->get('kind'),
                $request->request->get('current_billing_cycle')
            );

            return $this->view($subscription)->setFormat('json');
        } catch (NotFoundHttpException $e) {
            return $this->view(
                ['message' => 'Experience with #ID - '.$id.' doesn\'t exist'],
                Response::HTTP_NOT_FOUND
            )->setFormat('json');
        } catch (\RuntimeException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            )->setFormat('json');
        }
    }

    /**
     * @Rest\Get("/user/delete/{email}", name="api_user_delete")
     * @Rest\View()
     *
     * @param string $email
     * @return View|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function deleteUserAction(string $email)
    {
        try {

            if (!$this->getUser()->hasRole('ROLE_ADMIN')) {
                return $this->view(['message' => 'Access Denied'],Response::HTTP_UNAUTHORIZED)->setFormat('json');
            }

            /** @var User|null $user */
            $user = $this->testLogicService->getUserByEmail($email);

            if (is_null($user)) {
                return $this->view(['message' => 'User not found'],Response::HTTP_NOT_FOUND)->setFormat('json');
            }

            return $this->view($this->testLogicService->deleteUser($user))->setFormat('json');
        } catch (\RuntimeException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            )->setFormat('json');
        }
    }
}

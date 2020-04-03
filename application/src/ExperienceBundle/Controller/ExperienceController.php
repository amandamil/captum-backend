<?php

namespace ExperienceBundle\Controller;

use CoreBundle\Exception\FormValidationException;
use CoreBundle\Security\Voter\BaseVoter;
use ExperienceBundle\Entity\Experience;
use ExperienceBundle\Security\Voter\ExperienceVoter;
use SubscriptionBundle\Entity\Subscription;
use Swagger\Annotations as SWG;
use ExperienceBundle\Services\ExperienceService;
use Symfony\Component\HttpFoundation\{ Request, Response };
use FOS\RestBundle\Controller\{ AbstractFOSRestController, Annotations as Rest };
use FOS\RestBundle\View\View;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use UserApiBundle\Entity\User;

/**
 * Class DefaultController
 * @package ExperienceBundle\Controller
 */
class ExperienceController extends AbstractFOSRestController
{
    /** @var ExperienceService $experienceService */
    private $experienceService;

    /**
     * ExperienceController constructor.
     * @param ExperienceService $experienceService
     */
    public function __construct(ExperienceService $experienceService)
    {
        $this->experienceService = $experienceService;
    }

    /**
     * Create experience
     *
     * @Rest\Post("", name="api_create_expirience")
     * @Rest\View(serializerGroups={"experience"})
     *
     * @param Request $request
     * @return Experience|View
     * @throws \Exception
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @SWG\Post(
     *     summary="create new experience",
     *     description="create new experience",
     *     consumes={"multipart/form-data"},
     * )
     * @SWG\Response(
     *     response=201,
     *     description="Returns the success message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "success", "is_max_plan_reached": false}
     *     )
     * ),
     * @SWG\Response(
     *     response=400,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *          {"message": "Image is invalid"},
     *          {"message": "Video is missing! Please, add video from camera, camera roll or direct link"},
     *          {"message": "Video must be added from camera, camera roll, or via direct link"},
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
     * ),
     * @SWG\Response(
     *     response=401,
     *     description="Access denied",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "Access denied"}
     *     )
     * ),
     * @SWG\Parameter(
     *     name="image",
     *     in="formData",
     *     type="file",
     *     required=true,
     * )
     * @SWG\Parameter(
     *     name="video",
     *     in="formData",
     *     type="file",
     *     required=false,
     * )
     * @SWG\Parameter(
     *     name="video_url",
     *     in="formData",
     *     type="string",
     *     required=false,
     * )
     * @SWG\Parameter(
     *     name="title",
     *     in="formData",
     *     type="string",
     * )
     * @SWG\Parameter(
     *     name="contactName",
     *     in="formData",
     *     type="string",
     * )
     * @SWG\Parameter(
     *     name="phone",
     *     in="formData",
     *     type="string",
     * )
     * @SWG\Parameter(
     *     name="email",
     *     in="formData",
     *     type="string",
     * )
     */
    public function postAction(Request $request)
    {
        try {
            $this->denyAccessUnlessGranted(BaseVoter::ACCESS_CREATE, new Experience());

            /** @var User $user */
            $user = $this->getUser();

            /** @var Subscription $currentSubscription */
            $currentSubscription = $user->getActiveSubscription();

            if (!$currentSubscription) {
                return $this->view(['message' => 'You have no active subscriptions'], Response::HTTP_BAD_REQUEST);
            }

            $message = 'success';
            if (!$currentSubscription->getPackage()->isTrial() && $currentSubscription->getAvailableExperiencesCount() <= 0) {
                $message = 'You have reached limit of available experiences';
            }

            $this->experienceService->createExperience($request, $user, $currentSubscription, new Experience());

            $isMaxPlanReached = false;
            if (!$currentSubscription->getPackage()->isTrial()
                && $currentSubscription->getPackage()->getExperiencesNumber() === 10
                && $currentSubscription->getAvailableExperiencesCount() <= 0)
            {
                $isMaxPlanReached = true;
            }

            return $this->view(['message' => $message, 'is_max_plan_reached' => $isMaxPlanReached], Response::HTTP_CREATED);
        } catch (AccessDeniedException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_UNAUTHORIZED
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
     * @Rest\Get("/{id}", name="api_get_experience", requirements={"id": "\d+"})
     * @Rest\View(serializerGroups={"experience"})
     *
     * @param int $id
     * @return Experience|View
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
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
    public function getAction($id)
    {
        try {
            return $this->experienceService->get(intval($id));
        } catch (NotFoundHttpException $e) {
            return $this->view(
                ['message' => 'Experience with #ID - '.$id.' doesn\'t exist'],
                Response::HTTP_NOT_FOUND
            );
        } catch (\RuntimeException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * @Rest\Put("/{id}/status", name="api_change_experience_status", requirements={"id": "\d+"})
     * @Rest\View(serializerGroups={"experience"})
     *
     * @param Request $request
     * @param int $id
     * @return Experience|View
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @SWG\Put(
     *     summary="Change experience status",
     *     description="Change experience status",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns updated experience",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *          "video_url": "https://captum-dev.s3.amazonaws.com/420d74b7d6efec53200cf2b0a182fd7c.mp4",
     *          "image_url": "https://captum-dev.s3.amazonaws.com/420d74b7d6efec53200cf2b0a182fd7c.jpeg",
     *          "status": 1,
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
     * @SWG\Response(
     *     response=404,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "Experience with #ID - 1 doesn't exist"}
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
     * @SWG\Response(
     *     response=403,
     *     description="Access denied message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "Access denied"}
     *     )
     * ),
     * @SWG\Response(
     *     response=401,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "property invalid massage"}
     *     )
     * )
     * @SWG\Parameter(
     *     name="status",
     *     in="formData",
     *     type="integer",
     *     required=true,
     * )
     */
    public function changeStatusAction(Request $request , int $id) {
        try {
            /** @var Experience $entity */
            $entity = $this->getDoctrine()->getRepository('ExperienceBundle:Experience')->find($id);

            if (is_null($entity)) {
                throw new NotFoundHttpException();
            }

            $this->denyAccessUnlessGranted(BaseVoter::ACCESS_EDIT, $entity);

            return $this->experienceService->changeStatus($entity, $request);
        } catch (AccessDeniedException $e) {
            return $this->view(
                ['message' => 'Access denied'],
                Response::HTTP_FORBIDDEN
            );
        } catch (NotFoundHttpException $e) {
            return $this->view(
                ['message' => 'Experience with #ID - '.$id.' doesn\'t exist'],
                Response::HTTP_NOT_FOUND
            );
        } catch (BadRequestHttpException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        } catch (\RuntimeException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * @Rest\Post("/notification", name="transcoder_job_notification_handler")
     *
     * @return View
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function subscriptionTranscoderJobResult()
    {
        $data = file_get_contents('php://input');
        $notification = json_decode($data, true);

        if ($notification['Type'] == 'SubscriptionConfirmation') {
            // Confirm the subscription by sending a GET request to the SubscribeURL
            file_get_contents($notification['SubscribeURL']);
        }

        if ($notification['Type'] === 'Notification') {
            // Do whatever you want with the message body and data.
            $result = json_decode($notification['Message'],true);

            $this->experienceService->updateTranscoderJobStatus($result['jobId'], $result['state']);
        }

        return $this->view();

    }

    /**
     * @Rest\Get("/list", name="api_list_experience")
     * @Rest\View(serializerGroups={"experience_list"})
     *
     * @Rest\QueryParam(
     *     name="page",
     *     requirements="\d+"
     * )
     * @Rest\QueryParam(
     *     name="limit",
     *     requirements="\d+"
     * )
     * @Rest\QueryParam(
     *     name="status_filter",
     *     requirements="((-?\d?),?(-?\d))+"
     * )
     *
     * @param Request $request
     * @return array|View
     *
     * @SWG\Get(
     *     summary="Get list of user's experiences",
     *     description="Get list of user's experiences",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns list of user's experiences",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *                  "count": 3,
     *                  "pageCount": 1,
     *                  "experiences": "[experiences array]"
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
    public function listAction(Request $request)
    {
        try {

            $this->denyAccessUnlessGranted(BaseVoter::ACCESS_LIST, new Experience());

            $limit = $request->query->has('limit') ? intval($request->get('limit')) : 10;
            $page = $request->query->has('page') ? intval($request->get('page')) : 1;
            $filter = $request->query->has('status_filter') ? $request->get('status_filter') : '';

            return $this->experienceService->getUsersExperiences($this->getUser(), $page, $limit, $filter);
        } catch (NotFoundHttpException $e) {
            return $this->view(
                [],
                Response::HTTP_NOT_FOUND
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
     * @Rest\Post("/{id}", name="api_update_experience", requirements={"id": "\d+"})
     * @Rest\View(serializerGroups={"experience"})
     *
     * @param Request $request
     * @param int $id
     * @return Experience|View
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Exception
     *
     * @SWG\Post(
     *     summary="Update experience with new video",
     *     description="Update experience with new video",
     *     consumes={"multipart/form-data"},
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns updated experience",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *          "full_hd_video_url": null,
     *          "video_url": "https://captum-dev.s3.amazonaws.com/420d74b7d6efec53200cf2b0a182fd7c.mp4",
     *          "image_url": "https://captum-dev.s3.amazonaws.com/420d74b7d6efec53200cf2b0a182fd7c.jpeg",
     *          "status": 1,
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
     * @SWG\Response(
     *     response=404,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "Experience with #ID - 1 doesn't exist"}
     *     )
     * ),
     * @SWG\Response(
     *     response=403,
     *     description="Access denied message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "Access denied"}
     *     )
     * ),
     * @SWG\Response(
     *     response=401,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "property invalid massage"}
     *     )
     * )
     * @SWG\Response(
     *     response=400,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "property invalid massage"}
     *     )
     * )
     * @SWG\Parameter(
     *     name="title",
     *     in="formData",
     *     type="string",
     *     required=false,
     * )
     * @SWG\Parameter(
     *     name="contactName",
     *     in="formData",
     *     type="string",
     *     required=false,
     * )
     * @SWG\Parameter(
     *     name="phone",
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
     *     name="email",
     *     in="formData",
     *     type="string",
     *     required=false,
     * )
     * @SWG\Parameter(
     *     name="video",
     *     in="formData",
     *     type="file",
     *     required=false,
     * )
     * @SWG\Parameter(
     *     name="video_url",
     *     in="formData",
     *     type="string",
     *     required=false,
     * )
     */
    public function updateAction(Request $request , int $id) {
        try {
            /** @var Experience $entity */
            $entity = $this->getDoctrine()->getRepository('ExperienceBundle:Experience')->find($id);

            if (is_null($entity)) {
                throw new NotFoundHttpException();
            }

            /** @var Subscription $currentSubscription */
            $currentSubscription = $entity->getUser()->getActiveSubscription();

            if (is_null($currentSubscription)) {
                return $this->view(
                    ['message' => 'You have no active subscriptions'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $this->denyAccessUnlessGranted(ExperienceVoter::ACCESS_EDIT_VIDEO, $entity);

            return $this->experienceService->updateExperience($request, $entity);
        } catch (AccessDeniedException $e) {
            return $this->view(
                ['message' => 'Access denied'],
                Response::HTTP_FORBIDDEN
            );
        } catch (NotFoundHttpException $e) {
            return $this->view(
                ['message' => 'Experience with #ID - '.$id.' doesn\'t exist'],
                Response::HTTP_NOT_FOUND
            );
        } catch (BadRequestHttpException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
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
     * @Rest\Patch("/{id}", name="api_update_old_experience", requirements={"id": "\d+"})
     * @Rest\View(serializerGroups={"experience"})
     *
     * @param Request $request
     * @param int $id
     * @return Experience|View
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     *
     * @SWG\Patch(
     *     summary="Update experience",
     *     description="Update experience",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns updated experience",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *          "full_hd_video_url": null,
     *          "video_url": "https://captum-dev.s3.amazonaws.com/420d74b7d6efec53200cf2b0a182fd7c.mp4",
     *          "image_url": "https://captum-dev.s3.amazonaws.com/420d74b7d6efec53200cf2b0a182fd7c.jpeg",
     *          "status": 1,
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
     * @SWG\Response(
     *     response=404,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "Experience with #ID - 1 doesn't exist"}
     *     )
     * ),
     * @SWG\Response(
     *     response=403,
     *     description="Access denied message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "Access denied"}
     *     )
     * ),
     * @SWG\Response(
     *     response=401,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "property invalid massage"}
     *     )
     * )
     * @SWG\Response(
     *     response=400,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "property invalid massage"}
     *     )
     * )
     * @SWG\Parameter(
     *     name="title",
     *     in="formData",
     *     type="string",
     *     required=false,
     * )
     * @SWG\Parameter(
     *     name="contactName",
     *     in="formData",
     *     type="string",
     *     required=false,
     * )
     * @SWG\Parameter(
     *     name="phone",
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
     *     name="email",
     *     in="formData",
     *     type="string",
     *     required=false,
     * )
     */
    public function updateOldAction(Request $request , int $id) {
        try {
            /** @var Experience $entity */
            $entity = $this->getDoctrine()->getRepository('ExperienceBundle:Experience')->find($id);

            if (is_null($entity)) {
                throw new NotFoundHttpException();
            }

            $this->denyAccessUnlessGranted(BaseVoter::ACCESS_EDIT, $entity);

            return $this->experienceService->updateExperienceWithoutVideo($request, $entity);
        } catch (AccessDeniedException $e) {
            return $this->view(
                ['message' => 'Access denied'],
                Response::HTTP_FORBIDDEN
            );
        } catch (NotFoundHttpException $e) {
            return $this->view(
                ['message' => 'Experience with #ID - '.$id.' doesn\'t exist'],
                Response::HTTP_NOT_FOUND
            );
        } catch (BadRequestHttpException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
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
     * @Rest\Post("/{id}/report", name="api_report_experience", requirements={"id": "\d+"})
     *
     * @param Request $request
     * @param int $id
     * @return View
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     *
     * @SWG\Post(
     *     summary="Report experience",
     *     description="Report experience",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     *
     * @SWG\Response(
     *     response=201,
     *     description="returns nothing",
     * ),
     * @SWG\Response(
     *     response=400,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example=
     *          {"message": "error message"}
     *     )
     * ),
     * @SWG\Response(
     *     response=404,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "Experience with #ID - 1 doesn't exist"}
     *     )
     * ),
     * @SWG\Parameter(
     *     name="message",
     *     in="formData",
     *     type="string",
     *     required=false,
     * )
     */
    public function reportAction(Request $request, int $id)
    {
        try {
            /** @var Experience $entity */
            $entity = $this->getDoctrine()->getRepository('ExperienceBundle:Experience')->find($id);

            if (is_null($entity)) {
                return $this->view(
                    ['message' => 'Experience with #ID - ' . $id . ' doesn\'t exist'],
                    Response::HTTP_NOT_FOUND
                );
            }

            $this->experienceService->reportContent($entity, $request->request->get('message'));

            return $this->view(null, Response::HTTP_CREATED);
        } catch (\RuntimeException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * @Rest\Get("/examples", name="api_examples_experience")
     * @Rest\View(serializerGroups={"experience_list"})
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
     *
     * @SWG\Get(
     *     summary="Get list of experiences examples",
     *     description="Get list of experiences examples",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns list of experiences examples",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *                  "count": 3,
     *                  "pageCount": 1,
     *                  "experiences": "[experiences array]"
     *     }
     *     )
     * ),
     *
     * @SWG\Response(
     *     response=400,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example={"message": "Error message"}
     *     )
     * ),
     */
    public function getExamplesAction(Request $request)
    {
        try {
            $filters = $request->query->all();

            $page = $this->extractParam($filters, 'page', 1);
            $perPage = $this->extractParam($filters, 'limit', 10);

            if($page < 1 || $perPage < 1) {
                return $this->view(['message' => 'Wrong Query Params'],Response::HTTP_BAD_REQUEST);
            }

            return $this->experienceService->getExperienceExamples($page, $perPage);
        } catch (NotFoundHttpException $e) {
            return $this->view(
                [],
                Response::HTTP_NOT_FOUND
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

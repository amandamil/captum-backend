<?php

namespace ExperienceBundle\Services;

use ExperienceBundle\Model\VideoProcessingResult;
use FFMpeg\{
    FFMpeg,
    FFProbe\DataMapping\Stream
};
use CoreBundle\{
    Exception\FormValidationException,
    Services\ResponseService
};
use Doctrine\ORM\{EntityManager, EntityManagerInterface, NonUniqueResultException};
use ExperienceBundle\Command\{
    CreateTranscoderJobsCommand,
    UpdateTargetStatusVuforiaCommand,
    GetTargetSummaryReportCommand
};
use ExperienceBundle\Entity\{AbuseReport, Experience, TargetView};
use ExperienceBundle\Form\ {
    ExperienceType,
    ExperienceUpdateType
};
use ExperienceBundle\Repository\ {
    ExperienceRepository,
    TargetViewRepository
};
use function GuzzleHttp\Promise\all;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use JMS\JobQueueBundle\Entity\{
    Repository\JobManager,
    Job
};
use Money\{
    Currency,
    Money
};
use Pagerfanta\ {
    Adapter\DoctrineORMAdapter,
    Pagerfanta
};
use SubscriptionBundle\ {
    Entity\Subscription,
    Services\SubscriptionService
};
use Symfony\Component\{
    DependencyInjection\ContainerInterface,
    Form\FormFactoryInterface,
    HttpFoundation\Request,
    HttpKernel\Exception\BadRequestHttpException,
    HttpKernel\Exception\NotFoundHttpException
};
use UserApiBundle\Entity\{Balance, Charge, Device, User};
use Aws\Result;
use Imagick;
use \DateTime;
use \DateTimeZone;
use \DateInterval;
use UserApiBundle\Services\AwsSesService;
use UserApiBundle\Services\BalanceService;
use UserApiBundle\Services\NotificationService;
use VuforiaBundle\Services\VuforiaService;
use Doctrine\Common\Collections\Criteria;

/**
 * Class ExperienceService
 * @package ExperienceBundle\Services
 */
class ExperienceService
{
    /** @var EntityManager */
    private $em;

    /** @var ContainerInterface $container */
    private $container;

    /** @var FormFactoryInterface $formFactory */
    private $formFactory;

    /** @var ResponseService $responseService */
    private $responseService;

    /** @var ExperienceRepository $experienceRepository */
    private $experienceRepository;

    /** @var VuforiaService $vuforiaService */
    private $vuforiaService;

    /** @var AwsService $awsService */
    private $awsService;

    /** @var SubscriptionService $subscriptionService */
    private $subscriptionService;

    /** @var TargetViewRepository $targetViewRepository */
    private $targetViewRepository;

    /** @var JobManager $jobManager */
    private $jobManager;

    /** @var BalanceService */
    private $balanceService;

    /** @var AwsSesService $awsSesService */
    private $awsSesService;

    /** @var \Twig_Environment $templating */
    private $templating;

    /** @var NotificationService $notificationService */
    private $notificationService;

    /**
     * Constructor of class
     *
     * @param ContainerInterface     $container
     * @param EntityManagerInterface $em
     * @param FormFactoryInterface   $formFactory
     * @param ResponseService        $responseService
     * @param VuforiaService         $vuforiaService
     * @param AwsService             $awsService
     * @param SubscriptionService    $subscriptionService
     * @param JobManager             $jobManager
     * @param BalanceService         $balanceService
     * @param NotificationService    $notificationService
     *
     */
    public function __construct(
        ContainerInterface $container,
        EntityManagerInterface $em,
        FormFactoryInterface $formFactory,
        ResponseService $responseService,
        VuforiaService $vuforiaService,
        AwsService $awsService,
        SubscriptionService $subscriptionService,
        JobManager $jobManager,
        BalanceService $balanceService,
        NotificationService $notificationService
    )
    {
        $this->container = $container;
        $this->em = $em;
        $this->formFactory = $formFactory;
        $this->responseService = $responseService;
        $this->experienceRepository = $this->em->getRepository('ExperienceBundle:Experience');
        $this->vuforiaService = $vuforiaService;
        $this->awsService = $awsService;
        $this->subscriptionService = $subscriptionService;
        $this->targetViewRepository = $this->em->getRepository('ExperienceBundle:TargetView');
        $this->jobManager = $jobManager;
        $this->balanceService = $balanceService;
        $this->awsSesService = $this->container->get('user_api.ses.service');
        $this->templating = $this->container->get('twig');
        $this->notificationService = $notificationService;
    }

    /**
     * @param Request $request
     * @param User $user
     * @param Subscription $currentSubscription
     * @param Experience $entity
     * @return Experience
     * @throws \Exception
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createExperience(Request $request, User $user, Subscription $currentSubscription, Experience $entity) : Experience
    {
        $form = $this->formFactory->create(ExperienceType::class, $entity);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            throw new BadRequestHttpException('Wrong Request');
        }

        if (!$form->isValid()) {
            throw new FormValidationException($this->responseService->getFormError($form));
        }

        if ($currentSubscription->getPackage()->isTrial() && $currentSubscription->getAvailableExperiencesCount() <= 0) {
            throw new BadRequestHttpException('Trial plan allows to create only 3 experience');
        }

        /** @var VideoProcessingResult $videoValidationResult */
        $videoValidationResult = $this->validateVideo($request);

        if (!$videoValidationResult->isSuccess()) {
            throw new BadRequestHttpException($videoValidationResult->getMessage());
        }

        $image = $request->files->get('image');

        $imagick = new Imagick();
        $file_handle_image = fopen($image, 'a+');
        $imagick->readImageFile($file_handle_image);

        if ($imagick->getImageAlphaChannel() === Imagick::ALPHACHANNEL_ACTIVATE) {
            throw new BadRequestHttpException('Please add an image without alpha channel!');
        }

        if (!in_array($imagick->getImageDepth(),Experience::IMAGE_BIT_DEPTH)
            || !in_array($imagick->getImageColorspace(), [ Imagick::COLORSPACE_RGB, Imagick::COLORSPACE_GRAY, Imagick::COLORSPACE_SRGB ]))
        {
            throw new BadRequestHttpException('Only 8 bit gray scale or 24 bit RGB of file type JPG or PNG are allowed.');
        }

        if ($imagick->getImageWidth() < Experience::IMAGE_MIN_WIDTH) {
            throw new BadRequestHttpException('Image width is too low!');
        }

        $imagick->clear();

        $fileName = md5(uniqid());
        $imageFileKey = $fileName.'.'.$image->guessExtension();

        $response = $this->vuforiaService->createEmptyTarget(file_get_contents($image), $imageFileKey);
        if(!isset($response->target_id)) {
            throw new BadRequestHttpException('Unprocessable image');
        }

        $targetId = $response->target_id;
        $response = $this->vuforiaService->checkDuplicates($targetId);
        if (!isset($response->similar_targets)) {
            throw new BadRequestHttpException('Unknown target');
        }

        if (count($response->similar_targets) > 0) {
            throw new BadRequestHttpException('Duplicates found!');
        }

        $imageBucket = $this->container->getParameter('amazon_aws_s3_bucket_image');

        $entity->setUser($user);
        $entity->setTargetId($targetId);
        $entity->setImageKey($imageFileKey);
        $entity->setImageUrl('https://s3.amazonaws.com/'.$imageBucket.'/'.$imageFileKey);

        $videoFileKey = $fileName.'.'.$videoValidationResult->getExtension();

        $entity->setVideoKey($videoFileKey);

        if ($user->isExample()) {
            $entity->setIsExample(true);
        }

        $this->em->persist($entity);
        $this->em->flush();

        /** @var Promise $promiseImage */
        $promiseImage = $this->awsService->uploadImage(file_get_contents($image), $imageFileKey)
            ->then(function (Result $result) use ($entity) {
                $jobUpdateTarget = new Job(UpdateTargetStatusVuforiaCommand::NAME, [
                    sprintf('--%s=%d', UpdateTargetStatusVuforiaCommand::EXPERIENCE_OBJECT_ID, $entity->getId())
                ]);

                $date = (new DateTime())->add(new DateInterval('PT1M'));
                $jobUpdateTarget->setExecuteAfter($date);
                $this->em->persist($jobUpdateTarget);
                $this->em->flush();
        });

        /** @var string|null $videoStream */
        $videoStream = file_get_contents($videoValidationResult->getVideo());

        /** @var Stream $ffmpegStream */
        $ffmpegStream = $videoValidationResult->getFfmpegStream();

        /** @var Promise $promiseVideo */
        $promiseVideo = $this->uploadVideoToTranscoder($entity, $ffmpegStream, $videoStream, $fileName, $videoFileKey);

        $aggregate = all([$promiseImage, $promiseVideo]);
        $aggregate->wait();

        return $entity;
    }

    /**
     * @param int $id
     * @return Experience
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function get(int $id) : Experience
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
        $entity = $this->experienceRepository->matching($criteria)->first();

        if (is_null($entity) || !$entity) {
            throw new NotFoundHttpException();
        }

        try {
            $openJob = $this->jobManager->findOpenJobForRelatedEntity(GetTargetSummaryReportCommand::NAME, $entity);

            if (is_null($openJob)) {
                $jobSummaryReport = new Job(GetTargetSummaryReportCommand::NAME, [
                    sprintf('--%s=%d', GetTargetSummaryReportCommand::EXPERIENCE_OBJECT_ID, $entity->getId()),
                ]);

                $jobSummaryReport->addRelatedEntity($entity);

                $this->em->persist($jobSummaryReport);
                $this->em->flush();

            }
        } catch (NonUniqueResultException $exception) {
            return $entity;
        }

        return $entity;
    }

    /**
     * @param int $experienceId
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function updateTargetStatus(int $experienceId) : void
    {
        /** @var Experience $experience */
        $experience = $this->experienceRepository->find($experienceId);
        $response = $this->vuforiaService->getTargetImage($experience);

        if (isset($response->status)) {
            if ($response->status === 'success' && $response->target_record->tracking_rating >= 2) {
                $experience->setVuforiaStatus(Experience::VUFORIA_ACTIVE);

                /** @var Subscription $currentSubscription */
                $currentSubscription = $experience->getUser()->getActiveSubscription();

                if (!$currentSubscription) {
                    return;
                }

                if ($experience->getJobStatus() === Experience::TRANSCODER_JOB_STATUS_COMPLETE) {
                    if (!$currentSubscription->getPackage()->isTrial()
                        && $currentSubscription->getAvailableExperiencesWithoutCurrentCount($experience) <= 0)
                    {
                        $experience->setStatus(Experience::EXPERIENCE_DISABLED);
                        $this->vuforiaService->updateTarget($experience,0);
                        $this->notificationService->sendExperienceWentDisabled($experience->getUser(), $experience->formattedTitle());
                    } else {
                        $experience->setStatus(Experience::EXPERIENCE_ACTIVE);
                        $this->vuforiaService->updateTarget($experience, 1);
                        $this->notificationService->sendExperienceWentActive($experience->getUser(), $experience->formattedTitle());
                    }
                }
            }

            if ($response->status === 'processing') {
                $jobUpdateTarget = new Job(UpdateTargetStatusVuforiaCommand::NAME, [
                    sprintf('--%s=%d', UpdateTargetStatusVuforiaCommand::EXPERIENCE_OBJECT_ID, $experience->getId())
                ]);

                $date = (new DateTime())->add(new DateInterval('PT1M'));
                $jobUpdateTarget->setExecuteAfter($date);
                $this->em->persist($jobUpdateTarget);
            }

            if ($response->status === 'success' && $response->target_record->tracking_rating < 2) {
                $this->rejectExperience($experience, 'Quality of the target image is too low. Please use a different one');
            }

            if ($response->status === 'failed') {
                $this->rejectExperience($experience, 'failed');
            }

            $experience->setRating($response->target_record->tracking_rating);
            $this->em->persist($experience);
            $this->em->flush();
        }
    }

    /**
     * @param int $experienceId
     * @param string $fileName
     * @param int $rotate
     * @param int $width
     * @param int $height
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function createExperienceTranscoderJob(int $experienceId, string $fileName, int $rotate, int $width, int $height) : void
    {
        /** @var Experience $experience */
        $experience = $this->experienceRepository->find($experienceId);
        if ($experience->getStatus() === Experience::EXPERIENCE_REJECTED) {
            exit;
        }

        $fullHdKey = null;

        switch ($width) {
            case 7680: // 8K UHD
            case 4096: // DCI 4K
            case 3840: // 4K UHD
            case 1920: // 1080p || 1080i
                $fullHdKey = (string)$fileName.'_1080.mp4';
                $hdKey = (string)$fileName.'_720.mp4';
                $outputs = [
                    [
                        'Key' => $fullHdKey,
                        'Rotate' => (string)$rotate,
                        'PresetId' => $this->container->getParameter('amazon_full_hd_preset_id'),
                        'Width' => (string)$width,
                        'Height' => (string)$height,
                    ],
                    [
                        'Key' => $hdKey,
                        'Rotate' => (string)$rotate,
                        'PresetId' => $this->container->getParameter('amazon_hd_preset_id'),
                        'Width' => (string)$width,
                        'Height' => (string)$height,
                    ]
                ];
                break;
            case 1280: // 720p
                $hdKey = (string)$fileName.'_720.mp4';
                $outputs = [
                    [
                        'Key' => $hdKey,
                        'Rotate' => (string)$rotate,
                        'PresetId' => $this->container->getParameter('amazon_hd_preset_id'),
                        'Width' => (string)$width,
                        'Height' => (string)$height,
                    ]
                ];
                break;
            default: // 480p || 576p OR lower
                $hdKey = (string)$fileName.'_480.mp4';
                $outputs = [
                    [
                        'Key' => $hdKey,
                        'Rotate' => (string)$rotate,
                        'PresetId' => $this->container->getParameter('amazon_standard_preset_id'),
                        'Width' => (string)$width,
                        'Height' => (string)$height,
                    ]
                ];
                break;
        }

        /** @var string $transcodedBucketName */
        $transcodedBucketName = $this->container->getParameter('amazon_aws_s3_bucket_transcoded');

        $experience->setTranscodedUrlHDKey($hdKey);
        $experience->setTranscodedUrlHD('https://s3.amazonaws.com/'.$transcodedBucketName.'/tc/'.$hdKey);

        if (!is_null($fullHdKey)) {
            $experience->setTranscodedUrlFullHDKey($fullHdKey);
            $experience->setTranscodedUrlFullHD('https://s3.amazonaws.com/'.$transcodedBucketName.'/tc/'.$fullHdKey);
        }

        $jobId = $this->awsService->createJob($experience->getVideoKey(), $outputs);
        $experience->setJobStatus(Experience::TRANSCODER_JOB_STATUS_PROGRESSING);
        $experience->setJobId($jobId);

        $this->em->persist($experience);
        $this->em->flush();
    }

    /**
     * @param User $user
     * @param int $page
     * @param int $limit
     * @param string $filter
     * @return array
     */
    public function getUsersExperiences(User $user, int $page, int $limit, string $filter): array
    {

        if($page < 1 || $limit < 1) throw new BadRequestHttpException('Wrong Query Params');
        $matches = [];
        if(!empty($filter) || $filter === "0") {
            preg_match('/^((-?\d?),?(-?\d))+$/', $filter, $matches);

            if (!empty($matches)) $filter = explode(',', $filter);
            else throw new BadRequestHttpException('Wrong Query Params');
        }

        $query = $this->experienceRepository->findByUser($user, $filter);

        $adapter = new DoctrineORMAdapter($query);
        $paginator = new Pagerfanta($adapter);

        $paginator->setAllowOutOfRangePages(true);

        $paginator->setMaxPerPage($limit);
        $paginator->setCurrentPage($page);

        $paginator->getNbPages();

        $experiences = (array)$paginator->getCurrentPageResults();
        usort($experiences, function (Experience $a, Experience $b) {
            $aStatus = $a->getStatus();
            $bStatus = $b->getStatus();

            if ($aStatus == $bStatus) {
                return 0;
            }

            if (($aStatus == 0 && $bStatus == -1) || ($aStatus == -1 && $bStatus == 0)) {
                return 1;
            }

            return ($aStatus < $bStatus) ? -1 : 1;
        });

        $pagerArray = [
            "count" => $paginator->getNbResults(),
            "pageCount" => $paginator->getNbPages(),
            "experiences" => $experiences
        ];

        return $pagerArray;
    }

    /**
     * @param Experience $entity
     * @param Request $request
     * @return Experience
     * @throws \Exception
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function changeStatus(Experience $entity, Request $request) : Experience
    {
        $status = intval($request->request->get('status'));
        if ($entity->getStatus() == Experience::EXPERIENCE_PENDING) {
            throw new BadRequestHttpException('Experience is being processed');
        }

        if ($entity->getStatus() == Experience::EXPERIENCE_DELETED) {
            throw new BadRequestHttpException('Experience is deleted');
        }

        /** @var User $user */
        $user = $entity->getUser();

        /** @var Subscription $currentSubscription */
        $currentSubscription = $user->getActiveSubscription();

        if (!$currentSubscription && $status !== Experience::EXPERIENCE_DELETED) {
            throw new BadRequestHttpException('You have no active subscriptions');
        }

        if ($currentSubscription) {
            $availableExperiences = $currentSubscription->getAvailableExperiencesCount();

            if ($currentSubscription->getPackage()->isTrial()
                && in_array($status, [Experience::EXPERIENCE_PENDING, Experience::EXPERIENCE_ACTIVE, Experience::EXPERIENCE_DISABLED])
                && $availableExperiences <= 0)
            {
                if ($status === Experience::EXPERIENCE_DISABLED) {
                    throw new BadRequestHttpException('The trial plan only allows you to delete the experience');
                }

                if ($status === Experience::EXPERIENCE_PENDING) {
                    throw new BadRequestHttpException('This action is unavailable on free trial plan');
                }

                throw new BadRequestHttpException('Trial plan allows to create only 3 experience');
            }

            if ($status === Experience::EXPERIENCE_ACTIVE
                && $entity->getStatus() === Experience::EXPERIENCE_DISABLED
                && $availableExperiences <= 0
            ) {
                throw new BadRequestHttpException('You have reached limit of available experiences');
            }

            $free = $user->getViewsLeftFreeNumber($currentSubscription);
            $paid = $user->getViewsLeftPaidNumber($currentSubscription);

            if ($status === Experience::EXPERIENCE_ACTIVE && $free <= 0 && $paid <= 0) {
                throw new BadRequestHttpException('You have reached limit of available target views');
            }

            if (!in_array($status, [Experience::EXPERIENCE_ACTIVE, Experience::EXPERIENCE_DISABLED, Experience::EXPERIENCE_DELETED])) {
                throw new BadRequestHttpException('Wrong status');
            }

            if ($entity->getStatus() == Experience::EXPERIENCE_REJECTED && $status != Experience::EXPERIENCE_DELETED) {
                throw new BadRequestHttpException('The rejected experiences can only be deleted');
            }

            if (in_array($status, [Experience::EXPERIENCE_DISABLED, Experience::EXPERIENCE_DELETED, Experience::EXPERIENCE_ACTIVE])) {
                /** @var Job $openJob */
                $openJob = $this->jobManager->findOpenJobForRelatedEntity(GetTargetSummaryReportCommand::NAME, $entity);

                if ($status === Experience::EXPERIENCE_ACTIVE && $openJob) {
                    $openJob->setState(Job::STATE_CANCELED);

                    $this->em->persist($openJob);
                    $this->em->flush();
                }

                if (is_null($openJob)) {
                    $jobSummaryReport = new Job(GetTargetSummaryReportCommand::NAME, [
                        sprintf('--%s=%d', GetTargetSummaryReportCommand::EXPERIENCE_OBJECT_ID, $entity->getId()),
                    ]);

                    $date = $status === Experience::EXPERIENCE_ACTIVE
                        ? new DateTime()
                        : (new DateTime())->add(new DateInterval('PT1H'));

                    $jobSummaryReport->addRelatedEntity($entity);
                    $jobSummaryReport->setExecuteAfter($date);

                    $this->em->persist($jobSummaryReport);
                }
            }

            if (!is_null($entity->getTargetId())) {
                if ($status === Experience::EXPERIENCE_ACTIVE) {
                    $response = $this->vuforiaService->checkDuplicates($entity->getTargetId());

                    if (isset($response->similar_targets) && count($response->similar_targets) > 0) {
                        throw new BadRequestHttpException('Duplicates found!');
                    }
                }

                $response = $this->vuforiaService->updateTarget($entity,$status === Experience::EXPERIENCE_ACTIVE ? 1 : 0);
                if ($response && $response->result_code != "Success") {
                    $entity->setVuforiaRejectMessage($response->result_code);

                    if ($response->result_code === 'UnknownTarget' && in_array($status, [Experience::EXPERIENCE_ACTIVE, Experience::EXPERIENCE_DISABLED])) {
                        $status = Experience::EXPERIENCE_REJECTED;
                    }
                }
            }

            if ($status === Experience::EXPERIENCE_DELETED) {
                $this->deleteFromBucket($entity);
            }
        }

        $entity->setStatus($status);
        $this->em->flush();

        return $entity;
    }

    /**
     * @param string $jobId
     * @param string $status
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function updateTranscoderJobStatus(string $jobId, string $status) : void
    {
        /** @var Experience $experience */
        $experience = $this->experienceRepository->findOneBy(['jobId' => $jobId]);

        if (!is_null($experience) && $experience->getStatus() !== Experience::EXPERIENCE_REJECTED) {
            $experience->setJobStatus($status);

            switch ($status) {
                case Experience::TRANSCODER_JOB_STATUS_COMPLETE:

                    /** @var Subscription $currentSubscription */
                    $currentSubscription = $experience->getUser()->getActiveSubscription();

                    if (!$currentSubscription) {
                        return;
                    }

                    $this->awsService->deleteOriginalVideoObject($experience->getVideoKey())->wait();

                    if ($experience->getVuforiaStatus() === Experience::VUFORIA_ACTIVE) {
                        if (!$currentSubscription->getPackage()->isTrial()
                            && $currentSubscription->getAvailableExperiencesWithoutCurrentCount($experience) <= 0)
                        {
                            if (is_null($experience->getPreviousStatus())) {
                                $experience->setStatus(Experience::EXPERIENCE_DISABLED);
                                $this->vuforiaService->updateTarget($experience,0);
                                $this->notificationService->sendExperienceWentDisabled($experience->getUser(), $experience->formattedTitle());
                            } else {
                                $experience->setStatus($experience->getPreviousStatus());
                                $experience->setPreviousStatus(null);
                                $this->notificationService->sendExperienceVideoUpdateFinished($experience->getUser(), $experience->formattedTitle());
                            }
                        } else {
                            if (is_null($experience->getPreviousStatus())) {
                                $experience->setStatus(Experience::EXPERIENCE_ACTIVE);
                                $this->vuforiaService->updateTarget($experience,1);
                                $this->notificationService->sendExperienceWentActive($experience->getUser(), $experience->formattedTitle());
                            } else {
                                $experience->setStatus($experience->getPreviousStatus());
                                $experience->setPreviousStatus(null);
                                $this->notificationService->sendExperienceVideoUpdateFinished($experience->getUser(), $experience->formattedTitle());
                            }
                        }
                    }

                    break;
                case Experience::TRANSCODER_JOB_STATUS_ERROR:
                    $experience->setStatus(Experience::EXPERIENCE_REJECTED);
                    $this->vuforiaService->updateTarget($experience,0);
                    $this->notificationService->sendExperienceWentRejected($experience->getUser(),$experience->formattedTitle());
                    break;
            }

            $this->em->persist($experience);
            $this->em->flush();
        }
    }

    /**
     * @param Stream $videoStream
     * @return int|null
     */
    private function checkVideoRotate(Stream $videoStream) : ?int
    {
        if (!$videoStream->has('tags')) {
            return null;
        }

        $tags = $videoStream->get('tags');
        if (!isset($tags['rotate'])) {
            return null;
        }

        return (int)$tags['rotate'];
    }

    /**
     * @param Stream $videoStream
     * @return bool
     */
    private function isDurationExceeds(Stream $videoStream): bool
    {
        return (int)$videoStream->get('duration') > Experience::VIDEO_MAX_DURATION;
    }

    /**
     * @param string $url
     * @return int
     */
    private function isFileSizeExceeds(string $url): int
    {
        stream_context_set_default(['http' => ['method' => 'HEAD']]);
        $head = array_change_key_case(get_headers($url,1));

        $size = isset($head['content-length']) ? (int)$head['content-length'] : 0;

        stream_context_set_default(['http' => ['method' => 'GET']]);

        // cannot retrieve file size, return "-1"
        if (!$size) {
            return -1;
        }

        return (int)($size > Experience::VIDEO_MAX_SIZE_BYTES);
    }

    /**
     * @param string $videoPath
     * @return Stream|null
     */
    private function getFFMpegVideoStream(string $videoPath) : ?Stream
    {
        /** @var FFMpeg $ffmpeg */
        $ffmpeg = $this->container->get('dubture_ffmpeg.ffmpeg');

        /** @var \FFMpeg\Media\Video $video */
        $video = $ffmpeg->open($videoPath);

        return $ffmpeg->getFFProbe()->streams($video->getPathfile())->videos()->first();
    }

    /**
     * @param Experience $experience
     * @param string|null $errorMessage
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function rejectExperience(Experience $experience, string $errorMessage = null): void
    {
        $experience->setStatus(Experience::EXPERIENCE_REJECTED);
        $experience->setVuforiaRejectMessage($errorMessage);
        $experience->setVuforiaStatus(Experience::VUFORIA_REJECTED);

        if (!is_null($experience->getTargetId())) {
            $this->vuforiaService->updateTarget($experience,0);
        }

        $this->notificationService->sendExperienceWentRejected($experience->getUser(),$experience->formattedTitle());
    }

    /**
     * @param Request    $request
     * @param Experience $entity
     * @return Experience
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function updateExperience(Request $request, Experience $entity) : Experience
    {
        $form = $this->formFactory->create(ExperienceUpdateType::class, $entity);
        // crunch fix for ability update only a few submitted fields
        $form->submit($request->request->all(), false);

        if (!$form->isSubmitted()) {
            throw new BadRequestHttpException('Wrong Request');
        }

        if (!$form->isValid()) {
            throw new FormValidationException($this->responseService->getFormError($form));
        }

        if ($request->files->has('video') || $request->request->has('video_url')) {
            /** @var VideoProcessingResult $videoValidationResult */
            $videoValidationResult = $this->validateVideo($request);

            if (!$videoValidationResult->isSuccess()) {
                throw new BadRequestHttpException($videoValidationResult->getMessage());
            }

            $videoPromises = [];
            $videoPromises[] = $this->awsService->deleteOriginalVideoObject($entity->getVideoKey());
            $videoPromises[] = $this->awsService->deleteTranscodedVideoObject($entity->getTranscodedUrlHDKey(), $entity->getTranscodedUrlFullHDKey());

            $aggregate = all($videoPromises);
            $aggregate->wait();

            $entity->setTranscodedUrlHD(null);
            $entity->setTranscodedUrlHDKey(null);
            $entity->setTranscodedUrlFullHD(null);
            $entity->setTranscodedUrlFullHDKey(null);
            $entity->setPreviousStatus($entity->getStatus());
            $entity->setStatus(Experience::EXPERIENCE_PENDING);
            $this->em->persist($entity);

            /** @var Promise $promiseVideo */
            $this->uploadVideoToTranscoder(
                $entity,
                $videoValidationResult->getFfmpegStream(),
                file_get_contents($videoValidationResult->getVideo()),
                basename($entity->getVideoKey(), '.'.$videoValidationResult->getExtension()),
                $entity->getVideoKey())
            ->wait();
        }

        $this->em->persist($entity);
        $this->em->flush();

        return $entity;
    }

    /**
     * @param Request $request
     * @param Experience $entity
     * @return Experience
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function updateExperienceWithoutVideo(Request $request, Experience $entity) : Experience
    {
        $form = $this->formFactory->create(ExperienceUpdateType::class, $entity, ['method' => 'patch']);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            throw new BadRequestHttpException('Wrong Request');
        }

        if ($form->isValid()) {
            $this->em->persist($entity);
            $this->em->flush();

            return $entity;
        }

        throw new FormValidationException($this->responseService->getFormError($form));
    }

    /**
     * Update recognition statistic
     *
     * @param integer $experienceId
     * @return void
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function updateRecognitionStatistic(int $experienceId): void
    {
        /** @var Experience $experience */
        $experience = $this->experienceRepository->find($experienceId);
        if ($experience->getStatus() === Experience::EXPERIENCE_REJECTED) {
            exit;
        }

        $summary = $this->vuforiaService->getTargetSummaryReport($experience);

        if ($summary->result_code === 'Success') {
            $totalRecognitions = $summary->total_recos;

            if ($experience->getRating() === 0) {
                $experience->setRating($summary->tracking_rating);
                $this->em->persist($experience);
                $this->em->flush();
            }

            /** @var User $user */
            $user = $experience->getUser();

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

                    if ($todayPreviousViews === $recognitions) { // if $todayPreviousViews & $recognitions are equal, it means no new views
                        return;
                    }
                }

                $recoEntity->setViews($recognitions);

                $this->em->persist($recoEntity);
                $user->addTargetView($recoEntity);
                $this->em->flush();

                if ($experience->isExample()) {
                    return;
                }

                /** @var Balance $balance */
                $balance = $user->getBalance();

                /** @var int $freeLeft */
                $freeLeft = $user->getViewsLeftFreeNumber($subscription);
                $paidLeft = $user->getViewsLeftPaidNumber($subscription);

                // PUSH TYPE: limit percent reached
                if ($paidLeft >= 0) {
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
                            return;
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

                    if ($paidLeftAfterCharge <= 0 && $freeLeftPrevious < 0) {
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
        }
    }

    /**
     * @param Experience $experience
     * @param string|null $message
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function reportContent(Experience $experience, ?string $message): void
    {
        $report = new AbuseReport();
        $report->setExperience($experience);
        $report->setMessage($message);

        $this->em->persist($report);
        $this->em->flush();

        $this->awsSesService->sendEmail(
            $this->container->getParameter('admin_email'),
            $this->templating->render("emails/abuse_report.html.twig", ['experience' => $experience, 'user' => $experience->getUser()]),
            'Offensive/inappropriate content reported!'
        );
    }

    /**
     * @return array
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function rejectProcessingExperiences(): array
    {
        $ids = [];
        $processingExperiences = $this->experienceRepository->findBy(['status' => Experience::EXPERIENCE_PENDING]);

        /** @var Experience $experience */
        foreach ($processingExperiences as $experience) {
            $diff = $experience->getUpdatedAt()->diff(new \DateTime());
            $hours = $diff->h;
            $hours = $hours + ($diff->days*24);

            if ($hours > 3) {
                $experience->setStatus(Experience::EXPERIENCE_REJECTED);
                $this->em->persist($experience);
                $this->em->flush();

                $ids[] = $experience->getId();

                if (!is_null($experience->getTargetId())) {
                    $this->vuforiaService->updateTarget($experience,0);
                }
            }
        }

        return $ids;
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function deleteRejectedExperiences(): array
    {
        $ids = [];
        $processingExperiences = $this->experienceRepository->findBy(['status' => Experience::EXPERIENCE_REJECTED]);

        /** @var Experience $experience */
        foreach ($processingExperiences as $experience) {
            $diff = $experience->getUpdatedAt()->diff(new \DateTime());
            $days = $diff->days;

            if ($days >= 3) {
                $experience->setStatus(Experience::EXPERIENCE_DELETED);
                $this->em->persist($experience);
                $this->em->flush();

                $this->deleteFromBucket($experience);

                $ids[] = $experience->getId();

                if (!is_null($experience->getTargetId())) {
                    $this->vuforiaService->updateTarget($experience,0);
                }
            }
        }

        return $ids;
    }

    /**
     * @return array
     */
    public function deleteMediaForDeletedExperiences(): array
    {
        $ids = [];
        $processingExperiences = $this->experienceRepository->findBy(['status' => Experience::EXPERIENCE_DELETED]);

        /** @var Experience $experience */
        foreach ($processingExperiences as $experience) {
            $this->deleteFromBucket($experience);
            $ids[] = $experience->getId();
        }

        return $ids;
    }

    /**
     * @param Experience $experience
     */
    public function deleteFromBucket(Experience $experience): void
    {
        $promises = [];
        $promises[] = $this->awsService->deleteImageObject($experience->getImageKey());
        $promises[] = $this->awsService->deleteOriginalVideoObject($experience->getVideoKey());
        $promises[] = $this->awsService->deleteTranscodedVideoObject($experience->getTranscodedUrlHDKey(),$experience->getTranscodedUrlFullHDKey());

        $aggregate = all($promises);
        $aggregate->wait();
    }

    /**
     * @param Request $request
     * @return VideoProcessingResult
     */
    private function validateVideo(Request $request): VideoProcessingResult
    {
        $result = new VideoProcessingResult();

        if (!$request->files->has('video') &&
            (!$request->request->has('video_url') || empty($request->request->get('video_url')))) {
            $result->setMessage('Video is missing! Please, add video from camera, camera roll or direct link');
            return $result;
        }

        if ($request->files->has('video') && $request->request->has('video_url')) {
            $result->setMessage('Video must be added from camera, camera roll, or via direct link');
            return $result;
        }

        if ($request->files->has('video') && !empty($request->files->get('video'))) {
            $video = $request->files->get('video');
            $extension = $video->getClientOriginalExtension();
        }

        if ($request->request->has('video_url')) {
            $video = $request->request->get('video_url');
            $urlParts = parse_url($video,PHP_URL_PATH);
            $pathParts = pathinfo($urlParts);
            $extension = $pathParts['extension'];

            if (empty($extension)) {
                $result->setMessage('Invalid URL');
                return $result;
            }

            $isFileSizeExceeds = $this->isFileSizeExceeds($video);

            if ($isFileSizeExceeds === -1) {
                $result->setMessage('Can\'t get file size, please check your video URL');
                return $result;
            }

            if ($isFileSizeExceeds === 1) {
                $result->setMessage('The file is too large. Allowed maximum size is '.Experience::VIDEO_MAX_SIZE);
                return $result;
            }
        }

        $ffmpegStream = $this->getFFMpegVideoStream($video);

        if (!$ffmpegStream->isVideo()) {
            $result->setMessage('Please, upload a valid video');
            return $result;
        }

        if (in_array($extension, ['webm'])) {
            $result->setMessage('Please, upload a valid video');
            return $result;
        }

        if ($this->isDurationExceeds($ffmpegStream)) {
            $result->setMessage('The video should be less than '.(Experience::VIDEO_MAX_DURATION).' seconds.');
            return $result;
        }

        $result->setSuccess(true);
        $result->setVideo($video);
        $result->setExtension($extension);
        $result->setFfmpegStream($ffmpegStream);

        return $result;
    }

    /**
     * @param Experience $entity
     * @param Stream $ffmpegStream
     * @param string $videoStream
     * @param string $fileName
     * @param string $videoFileKey
     * @return PromiseInterface
     */
    private function uploadVideoToTranscoder(
        Experience $entity,
        Stream $ffmpegStream,
        string $videoStream,
        string $fileName,
        string $videoFileKey): PromiseInterface
    {
        return $promiseVideo = $this->awsService->uploadVideo($videoStream, $videoFileKey)
            ->then(function (Result $result) use ($entity, $ffmpegStream, $fileName) {
                $rotation = $this->checkVideoRotate($ffmpegStream) ?? 0;
                $dimension = $ffmpegStream->getDimensions();

                $jobStartTranscoder = new Job(CreateTranscoderJobsCommand::NAME, [
                    sprintf('--%s=%d', CreateTranscoderJobsCommand::EXPERIENCE_OBJECT_ID, $entity->getId()),
                    sprintf('--%s=%d', CreateTranscoderJobsCommand::ROTATION, $rotation),
                    sprintf('--%s=%s', CreateTranscoderJobsCommand::FILENAME, $fileName),
                    sprintf('--%s=%d', CreateTranscoderJobsCommand::WIDTH, $dimension->getWidth()),
                    sprintf('--%s=%d', CreateTranscoderJobsCommand::HEIGHT, $dimension->getHeight()),
                ]);

                $this->em->persist($jobStartTranscoder);
                $this->em->flush();
            });
    }

    /**
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getExperienceExamples(int $page, int $limit): array
    {
        $query = $this->experienceRepository->getExamples();

        $adapter = new DoctrineORMAdapter($query);
        $paginator = new Pagerfanta($adapter);

        $paginator->setAllowOutOfRangePages(true);

        $paginator->setMaxPerPage($limit);
        $paginator->setCurrentPage($page);

        $pagerArray = [
            "count" => $paginator->getNbResults(),
            "pageCount" => $paginator->getNbPages(),
            "experiences" => (array)$paginator->getCurrentPageResults()
        ];

        return $pagerArray;
    }
}

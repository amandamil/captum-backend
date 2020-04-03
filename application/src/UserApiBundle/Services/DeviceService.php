<?php

namespace UserApiBundle\Services;

use Aws\Sns\Exception\SnsException;
use Aws\Sns\SnsClient;
use CoreBundle\Exception\FormValidationException;
use CoreBundle\Services\ResponseService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\Serializer;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UserApiBundle\Entity\Device;
use UserApiBundle\Entity\User;
use UserApiBundle\Form\DeviceType;
use UserApiBundle\Repository\DeviceRepository;

/**
 * Class DeviceService
 * @package UserApiBundle\Services
 */
class DeviceService
{
    /** @var EntityManager */
    private $em;

    /** @var ContainerInterface $container */
    private $container;

    /** @var FormFactoryInterface $formFactory */
    private $formFactory;

    /** @var ResponseService $responseService */
    private $responseService;

    /** @var DeviceRepository $deviceRepository */
    private $deviceRepository;

    /** @var SnsClient $sns */
    private $sns;

    /**
     * AuthService constructor.
     * @param ContainerInterface     $container
     * @param EntityManagerInterface $em
     * @param FormFactoryInterface   $formFactory
     */
    public function __construct(
        ContainerInterface $container,
        EntityManagerInterface $em,
        FormFactoryInterface $formFactory
    ) {
        $this->em = $em;
        $this->container = $container;
        $this->formFactory = $formFactory;
        $this->responseService = $this->container->get('core.response_service');
        $this->deviceRepository = $this->em->getRepository(Device::class);
        $this->sns = $this->container->get('aws.sns');
    }

    /**
     * Send push notification to devices by user.
     * @param User $user
     * @param $title
     * @param $message
     * @param array $data
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function sendPushNotificationByUser(User $user, $title, $message, $data = [])
    {
        $devices = $user->getDevices();

        $exception = null;
        foreach ($devices as $device) {
            try {
                $this->sendPushNotification($device, $title, $message, $data);
            } catch (SnsException $e) {
                // Each all device regardless of AWS SNS exceptions
                $exception = $e;
                continue;
            }
        }

        if (!is_null($exception)) {
            throw $exception;
        }
    }

    /**
     * TODO: Configure payload
     * Send push notification to device.
     * @param Device $device
     * @param $title
     * @param $message
     * @param array $data
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function sendPushNotification(Device $device, $title, $message, $data = [])
    {
        switch ($device->getPlatform()) {
            case Device::PLATFORM_ANDROID:
                $payload = json_encode([
                    'data' => [
                        'title' => $title,
                        'body' => $message,
                        'pushType' => $data['pushType']
                    ]
                ]);
                $message = json_encode([
                    'default' => $message,
                    'GCM' => $payload
                ]);
                break;
            case Device::PLATFORM_IOS:
                $payload = json_encode([
                    'aps' => [
                        'alert' => [
                            'title' => $title,
                            'body' => $message,
                            'pushType' => $data['pushType']
                        ]
                    ]
                ]);
                $message = json_encode([
                    'default' => $message,
                    'APNS_SANDBOX' => $payload,
                    'APNS' => $payload
                ]);
                break;
            default:
                throw new BadRequestHttpException('Unsupported platform');
        }

        try {
            $this->sns->publish([
                'TargetArn' => $device->getAwsArn(),
                'Message' => $message,
                'MessageStructure' => 'json'
            ]);
        } catch (SnsException $snsException) {
            // AWS error code "InvalidParameter" - not found endpoint.
            if ($snsException->getAwsErrorCode() === 'InvalidParameter') {
                // Remove this device.
                $this->em->remove($device);
                $this->em->flush();
            }
            // AWS error code "EndpointDisabled" - no more installed on the device
            elseif ($snsException->getAwsErrorCode() === 'EndpointDisabled') {
                // Remove this device.
                $this->em->remove($device);
                $this->em->flush();
                // Remove from AWS.
                $this->sns->deleteEndpoint([
                    'EndpointArn' => $device->getAwsArn()
                ]);
            } else {
                throw $snsException;
            }
        }
    }

    /**
     * Register (create or update) AWS ARN Endpoint
     * @param User $user
     * @param Device $device
     * @return string AWS ARN Endpoint
     */
    protected function registerAwsEndpoint(User $user, Device $device)
    {
        switch ($device->getPlatform()) {
            case Device::PLATFORM_ANDROID:
                $awsArnPlatform = $this->container->getParameter('amazon_aws_sns_arn_android');
                break;
            case Device::PLATFORM_IOS:
                $awsArnPlatform = $this->container->getParameter('amazon_aws_sns_arn_ios');
                break;
            default:
                throw new BadRequestHttpException('Unsupported platform');
        }

        if ($device->getAwsArn()) {
            // Check exist endpoint.
            try {
                $this->sns->getEndpointAttributes([
                    'EndpointArn' => $device->getAwsArn()
                ]);
            } catch (SnsException $exception) {
                if ($exception->getAwsErrorCode() === 'NotFound') {
                    // If not found set null.
                    $device->setAwsArn(null);
                }
            }
        }

        if ($device->getAwsArn()) {
            // Update endpoint or enable.
            $this->sns->setEndpointAttributes([
                'EndpointArn' => $device->getAwsArn(),
                'Attributes' => [
                    'Token' => $device->getDeviceToken(),
                    'Enabled' => 'true'
                ]
            ]);
        } else {
            // Create endpoint.
            try {
                $result = $this->sns->createPlatformEndpoint([
                    'PlatformApplicationArn' => $awsArnPlatform,
                    'Token' => $device->getDeviceToken(),
                    'CustomUserData' => json_encode([
                        'id' => $user->getId(),
                        'email' => $user->getEmail(),
                        'firstName' => $user->getFirstName(),
                        'lastName' => $user->getLastName()
                    ]),
                    'Attributes' => [
                        'Enabled' => 'true',
                        'UserId' => $user->getId(),
                    ]
                ]);
            } catch (SnsException $exception) {
                if ($exception->getAwsErrorCode() === 'InvalidParameter' && $exception->getCommand()->hasParam('Token') && $exception->getCommand()['Token'] === $device->getDeviceToken()) {
                    throw new BadRequestHttpException('Device token already exist in AWS SNS');
                }

                throw $exception;
            }
            $device->setAwsArn($result->get('EndpointArn'));
        }

        return $device->getAwsArn();
    }

    /**
     * Create user device
     * @param Request $request
     * @param User $user
     * @return Device
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function create(Request $request, User $user): Device
    {
        $device = $this->deviceRepository->findOneBy([
            'deviceToken' => $request->get('device_token')
        ]);

        if ($device) {
            throw new BadRequestHttpException('Device token already exist');
        }

        $device = new Device();
        $device->setUser($user);

        $form = $this->formFactory->create(DeviceType::class, $device);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            throw new BadRequestHttpException('Wrong Request');
        }

        if ($form->isValid()) {
            $this->registerAwsEndpoint($user, $device);

            $this->em->persist($device);
            $this->em->flush();

            return $device;
        }

        throw new FormValidationException($this->responseService->getFormError($form));
    }

    /**
     * Update user device by device IDs
     * @param Request $request
     * @param User $user
     * @param int $deviceId
     * @return Device
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function updateDeviceById(Request $request, User $user, int $deviceId)
    {
        $device = $this->deviceRepository->findOneBy([
            'id' => $deviceId,
            'user' => $user
        ]);

        if (!$device) {
            throw new NotFoundHttpException();
        }

        $form = $this->formFactory->create(DeviceType::class, $device, [
            'method' => 'PUT'
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            throw new BadRequestHttpException('Wrong Request');
        }

        if ($form->isValid()) {
            $this->registerAwsEndpoint($user, $device);

            $this->em->persist($device);
            $this->em->flush();

            return $device;
        }

        throw new FormValidationException($this->responseService->getFormError($form));
    }

    /**
     * Delete user device by token
     * @param User $user
     * @param $deviceToken
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function deleteByToken(User $user, $deviceToken)
    {
        $device = $this->deviceRepository->findOneBy([
            'user' => $user,
            'deviceToken' => $deviceToken
        ]);

        // If the device is not found.
        if (!$device) {
            return;
        }

        $this->sns->deleteEndpoint([
            'EndpointArn' => $device->getAwsArn()
        ]);

        $this->em->remove($device);
        $this->em->flush();
    }
}

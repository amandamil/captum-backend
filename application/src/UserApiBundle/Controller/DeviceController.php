<?php

namespace UserApiBundle\Controller;

use Aws\Sns\Exception\SnsException;
use CoreBundle\Exception\FormValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use CoreBundle\Security\Voter\BaseVoter;
use FOS\RestBundle\Controller\{ AbstractFOSRestController, Annotations as Rest };
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\Request;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Response;
use UserApiBundle\Entity\Device;
use UserApiBundle\Security\Voter\UserVoter;
use UserApiBundle\Services\AuthService;
use UserApiBundle\Services\DeviceService;
use Symfony\Component\Routing\Annotation\Route;
use Nelmio\ApiDocBundle\Annotation\Model;

/**
 * Class DeviceController
 * @package UserApiBundle\Controller
 */
class DeviceController extends AbstractFOSRestController
{
    /** @var AuthService $authService */
    private $authService;

    /** @var DeviceService $deviceService */
    private $deviceService;

    /**
     * DefaultController constructor.
     * @param AuthService $authService
     * @param DeviceService $deviceService
     */
    public function __construct(AuthService $authService, DeviceService $deviceService)
    {
        $this->authService = $authService;
        $this->deviceService = $deviceService;
    }

    /**
     * @Rest\Post("", name="api_user_device_create")
     * @Rest\View(serializerGroups={"device"})
     *
     * @param Request $request
     * @return Device|View
     *
     * @SWG\Post(
     *      summary="Registers new user device",
     *      description="Create new device",
     *      tags={"Device"},
     *      consumes={"application/x-www-form-urlencoded"},
     *      @SWG\Parameter(
     *          name="device_token",
     *          in="formData",
     *          type="string",
     *          required=true,
     *      ),
     *      @SWG\Parameter(
     *          name="platform",
     *          in="formData",
     *          type="string",
     *          required=true,
     *      ),
     *      @SWG\Parameter(
     *          name="version",
     *          in="formData",
     *          type="string",
     *          required=false,
     *      )
     * )
     * @SWG\Response(
     *     response=201,
     *     description="Returns device object",
     *     @Model(type=Device::class, groups={"device"})
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
     * ),
     * @SWG\Response(
     *     response=400,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example=
     *          { "message": "Wrong request" }
     *     )
     * )
     *
     */
    public function create(Request $request)
    {
        try {
            $user = $this->getUser();
            $this->denyAccessUnlessGranted(UserVoter::ACCESS_DEVICE, $user);

            $device = $this->deviceService->create($request, $user);

            return $this->view(
                $device,
                Response::HTTP_CREATED
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
        } catch (SnsException $exception) {
            return $this->view(
                ['message' => 'Wrong Request'],
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
     * @Rest\Put("{id}", name="api_user_device_update", requirements={"id": "\d+"})
     * @Rest\View(serializerGroups={"device"})
     *
     * @param Request $request
     * @param integer $id
     * @return Device|View
     *
     * @SWG\Put(
     *      summary="Update user device",
     *      description="Update user device",
     *      tags={"Device"},
     *      consumes={"application/x-www-form-urlencoded"},
     *      @SWG\Parameter(
     *          name="device_token",
     *          in="formData",
     *          type="string",
     *          required=true,
     *      ),
     *      @SWG\Parameter(
     *          name="platform",
     *          in="formData",
     *          type="string",
     *          required=true,
     *      ),
     *      @SWG\Parameter(
     *          name="version",
     *          in="formData",
     *          type="string",
     *          required=false,
     *      )
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns device object",
     *     @Model(type=Device::class, groups={"device"})
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
     * ),
     * @SWG\Response(
     *     response=400,
     *     description="Error message",
     *     @SWG\Schema(
     *        type="object",
     *        example=
     *          { "message": "Wrong request" }
     *     )
     * )
     *
     */
    public function update(Request $request, int $id)
    {
        try {
            $user = $this->getUser();
            $this->denyAccessUnlessGranted(UserVoter::ACCESS_DEVICE, $user);

            $device = $this->deviceService->updateDeviceById($request, $user, $id);

            return $this->view(
                $device,
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
        } catch (NotFoundHttpException $exception) {
            return $this->view(
                ['message' => 'Device not found'],
                Response::HTTP_NOT_FOUND
            );
        } catch (SnsException $exception) {
            return $this->view(
                ['message' => 'Wrong Request'],
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
     * @Rest\Delete("{token}", name="api_user_device_delete")
     * @Rest\View(serializerGroups={"device"})
     *
     * @param string $token
     * @return Device|View
     *
     * @SWG\Delete(
     *      summary="Delete user device",
     *      description="Delete user device",
     *      tags={"Device"}
     * )
     * @SWG\Response(
     *     response=204,
     *     description="Empty body"
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
     *        example=
     *          { "message": "Wrong request" }
     *     )
     * )
     *
     */
    public function delete($token)
    {
        try {
            $user = $this->getUser();
            $this->denyAccessUnlessGranted(UserVoter::ACCESS_DEVICE, $user);

            $this->deviceService->deleteByToken($user, $token);

            return $this->view(
                null,
                Response::HTTP_NO_CONTENT
            );
        } catch (AccessDeniedException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_UNAUTHORIZED
            );
        } catch (NotFoundHttpException $exception) {
            return $this->view(
                ['message' => 'Device not found'],
                Response::HTTP_NOT_FOUND
            );
        } catch (SnsException $exception) {
            return $this->view(
                ['message' => 'Wrong Request'],
                Response::HTTP_BAD_REQUEST
            );
        } catch(\RuntimeException $exception) {
            return $this->view(
                ['message' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }
}

<?php

namespace CoreBundle\Services;

use JMS\Serializer\SerializationContext;
use JMS\Serializer\Serializer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ResponseService
{
    /** @var ContainerInterface $container */
    private $container;

    /** @var Serializer $serializer */
    private $serializer;

    /**
     * ResponseService constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->serializer = $this->container->get('jms_serializer');
    }

    /**
     * @param Request $request
     * @param $payload
     * @return Response
     */
    public function prepareWithPayload(Request $request, $payload)
    {
        /**
         * Prepare response
         */
        $response = new JsonResponse($payload);
        $response->setStatusCode(200);
        return $response;
    }

    /**
     * @param Request $request
     * @param $message
     * @return Response
     */
    public function prepare(Request $request, $message)
    {
        /**
         * Prepare response
         */
        $response = new JsonResponse(['message' => $message]);
        $response->setStatusCode(200);
        return $response;
    }


    /**
     * @param Request $request
     * @param $message
     * @param $errors
     * @return Response
     */
    public function prepareError(Request $request, $message, $errors = null)
    {
        /**
         * Prepare response
         */
        if (null === $errors) {
            $response = new JsonResponse(['message' => $message]);
            $response->setStatusCode(400);
            return $response;
        }
        else {
            $response = new JsonResponse($this->flattenErrors($errors));
            $response->setStatusCode(422);
            return $response;
        }

    }

    /**
     * @param $errors
     * @return array
     */
    public function flattenErrors($errors)
    {
        $errorsArray = [];

        foreach ($errors as $error) {
            $errorsArray[$error->getOrigin()->getName()] = $error->getMessage();
        }

        return $errorsArray;
    }

    /**
     * @param FormInterface $form
     * @return string
     */
    public function getFormError(FormInterface $form)
    {
        foreach ($form->getErrors() as $error) {
            return $error->getMessage();
        }

        foreach ($form->all() as $childForm) {
            if ($childForm instanceof FormInterface) {
                if ($childError = $this->getFormError($childForm)) {
                    return $childError;
                }
            }
        }
    }

    /**
     * @param FormInterface $form
     * @return array
     */
    public function getErrorsForm(FormInterface $form) : array
    {
        $errors = [];
        foreach ($form->getErrors() as $error) {
            $errors[] = $error->getMessage();
        }

        foreach ($form->all() as $childForm) {
            if ($childForm instanceof FormInterface) {
                if ($childErrors = $this->getErrorsForm($childForm)) {
                    $errors[$childForm->getName()] = $childErrors;
                }
            }
        }

        return $errors;
    }

    /**
     * @param $data
     * @param $groups
     * @return mixed|string
     */
    public function serialize($data, $groups)
    {
        $context = new SerializationContext();
        $context->setSerializeNull(true);
        $context->setGroups($groups);

        return $this->serializer->serialize($data,'json', $context);
    }
}
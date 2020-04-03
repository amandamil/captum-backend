<?php

namespace FrontendBundle\Controller;

use FOS\RestBundle\Controller\{ AbstractFOSRestController, Annotations as Rest };
use FOS\RestBundle\View\View;
use Psr\Container\ContainerInterface;
use Swagger\Annotations as SWG;

/**
 * Class FrontendController
 * @package UserApiBundle\Controller
 */
class FrontendController extends AbstractFOSRestController
{
    /**
     * @return array
     */
    public static function getSubscribedServices()
    {
        return array_merge(parent::getSubscribedServices(), [
            'service_container' => ContainerInterface::class,
        ]);
    }

    /**
     * @Rest\Get("/privacy-policy", name="privacy-policy")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function privacyPolicyAction()
    {
        $view = $this->view()->setTemplate('/settings/frontend-privacy-policy.html.twig');
        return $this->handleView($view);
    }

    /**
     * @Rest\Get("/terms-&-conditions", name="terms-&-conditions")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function termsAndConditionsAction()
    {
        $view = $this->view()->setTemplate('/settings/frontend-terms-of-conditions.html.twig');
        return $this->handleView($view);
    }

    /**
     * @Rest\Get("/logo", name="captum-logo")
     * @return View
     *
     * @SWG\Get(
     *     summary="Get captum logo",
     *     description="Get captum logo",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     *
     * @SWG\Response(
     *     response=200,
     *     description="Returns captum logo",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *              "logo": "https://s3.amazonaws.com/captum-dev/logo-Captum.png"
     *        }
     *     )
     * )
     */
    public function getLogoAction()
    {
        return $this->view(['logo' => $this->get('service_container')->getParameter('amazon_captum_logo_url')])->setFormat('json');
    }

    /**
     * @Rest\Get("/logo-eps", name="captum-logo-eps")
     * @return View
     *
     * @SWG\Get(
     *     summary="Get eps captum logo",
     *     description="Get eps captum logo",
     *     consumes={"application/x-www-form-urlencoded"},
     * )
     *
     * @SWG\Response(
     *     response=200,
     *     description="Returns eps captum logo",
     *     @SWG\Schema(
     *        type="object",
     *        example={
     *              "logo": "https://s3.amazonaws.com/captum-dev/logo-Captum.png"
     *        }
     *     )
     * )
     */
    public function getLogoEpsAction()
    {
        return $this->view(['logo' => $this->get('service_container')->getParameter('amazon_captum_logo_eps_url')])->setFormat('json');
    }
}

<?php

namespace SubscriptionBundle\Services;

use ReceiptValidator\iTunes\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use ReceiptValidator\iTunes\Validator as iTunesValidator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Class AppstoreService
 * @package SubscriptionBundle\Services
 */
class AppstoreService
{
    /** @var ContainerInterface $container */
    private $container;

    /** @var iTunesValidator $validator */
    private $validator;

    /**
     * AppstoreService constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->validator = (new iTunesValidator(
            $this->container->getParameter('appstore_sandbox_enabled')
                ? iTunesValidator::ENDPOINT_SANDBOX
                : iTunesValidator::ENDPOINT_PRODUCTION
            ))
            ->setSharedSecret($this->container->getParameter('appstore_password'))
            ->setExcludeOldTransactions(true);
    }

    /**
     * @param string $receipt
     * @return \ReceiptValidator\iTunes\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function receiptVerification(string $receipt): ResponseInterface
    {
        try {
            return $this->validator->setReceiptData($receipt)->validate();
        } catch (\Exception $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }
}
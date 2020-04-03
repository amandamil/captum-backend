<?php

namespace UserApiBundle\Services;

use Aws\{
    Result, ElasticTranscoder\ElasticTranscoderClient, Ses\SesClient
};
use Aws\S3\{ S3ClientInterface };
use GuzzleHttp\Psr7\Stream;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AwsUploadService
 * @package ExperienceBundle\Services
 */
class AwsSesService
{
    const FROM = 'captum@captumapp.com';

    /** @var ContainerInterface $container */
    private $container;

    /** @var SesClient $awsSesClient */
    private $awsSesClient;

    /**
     * AwsUploadService constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->awsSesClient = new SesClient([
            'version' => '2010-12-01',
            'region'  => $this->container->getParameter('amazon_aws_s3_region'),
            'credentials' =>  [
                'key' => $this->container->getParameter('amazon_aws_s3_key'),
                'secret' => $this->container->getParameter('amazon_aws_s3_secret_key'),
            ]
        ]);

    }

    /**
     * @param string $email
     * @param string $message
     * @param string $subject
     * @return Result
     */
    public function sendEmail(string $email, string $message, string $subject) : Result
    {
        $charSet = 'UTF-8';

        return $this->awsSesClient->sendEmail([
            'Destination' => [
                'ToAddresses' => [$email],
            ],
            'Source' => self::FROM,
            'Message' => [
                'Body' => [
                    'Html' => [
                        'Charset' => $charSet,
                        'Data' => $message,
                    ],
                ],
                'Subject' => [
                    'Charset' => $charSet,
                    'Data' => $subject,
                ],
            ],
        ]);
    }
}
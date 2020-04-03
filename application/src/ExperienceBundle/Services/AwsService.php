<?php

namespace ExperienceBundle\Services;

use Aws\ElasticTranscoder\ElasticTranscoderClient;
use Aws\S3\{ S3ClientInterface };
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Stream;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AwsUploadService
 * @package ExperienceBundle\Services
 */
class AwsService
{
    /** @var ContainerInterface $container */
    private $container;

    /** @var S3ClientInterface $awsS3Client */
    private $awsS3Client;

    /** @var mixed $awsBucketImage */
    private $awsBucketImage;

    /** @var mixed $awsBucketVideo */
    private $awsBucketVideo;

    /** @var integer$piplineId */
    private $piplineId;

    /** @var ElasticTranscoderClient $elasticTranscoder */
    private $elasticTranscoder;

    /** @var mixed $awsBucketTranscoded */
    private $awsBucketTranscoded;

    /**
     * AwsUploadService constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->awsS3Client = $this->container->get('aws.s3');
        $this->awsBucketImage = $this->container->getParameter('amazon_aws_s3_bucket_image');
        $this->awsBucketVideo = $this->container->getParameter('amazon_aws_s3_bucket_video');
        $this->awsBucketTranscoded = $this->container->getParameter('amazon_aws_s3_bucket_transcoded');
        $this->piplineId = $this->container->getParameter('amazon_transcoder_pipeline_id');
        $this->elasticTranscoder = ElasticTranscoderClient::factory([
            'credentials' => [
                'key'    => $this->container->getParameter('amazon_aws_s3_key'),
                'secret' => $this->container->getParameter('amazon_aws_s3_secret_key'),
            ],
            'region' => $this->container->getParameter('amazon_aws_s3_region'),
            'version' => '2012-09-25',
        ]);
    }

    /**
     * @param string $file
     * @param string $fileKey
     * @return Promise
     */
    public function uploadImage(string $file, string $fileKey) : Promise
    {
        return $this->awsS3Client->uploadAsync($this->awsBucketImage, $fileKey, $file, 'public-read');
    }

    /**
     * @param string $file
     * @param string $fileKey
     * @return Promise
     */
    public function uploadVideo(string $file, string $fileKey) : Promise
    {
        return $this->awsS3Client->uploadAsync($this->awsBucketVideo, $fileKey, $file, 'public-read');
    }

    /**
     * @param string $fileKey
     * @return Stream
     */
    public function getObject(string $fileKey) : Stream
    {
        $result = $this->awsS3Client->getObject([
            'Bucket' => $this->awsBucketVideo,
            'Key' => $fileKey,
        ]);

        return $result['Body'];
    }

    /**
     * @param string $inputKey
     * @param array $outputs
     * @return string
     */
    public function createJob(string $inputKey, array $outputs) : string
    {
        $job = $this->elasticTranscoder->createJob([
            'PipelineId' => $this->piplineId,
            'OutputKeyPrefix' => 'tc/',
            'Input' => [
                'Key' => $inputKey,
            ],
            'Outputs' => $outputs,
        ]);

        $jobData = $job->get('Job');

        return $jobData['Id'];
    }

    /**
     * @param string $jobId
     * @return mixed|null
     */
    public function checkJobStatus(string $jobId)
    {
        $jobStatusRequest = $this->elasticTranscoder->readJob(['Id' => $jobId]);
        $jobData = $jobStatusRequest->get('Job');

        if ($jobData['Status'] !== 'progressing' && $jobData['Status'] !== 'submitted') {
            return $jobData['Status'];
        }

        return null;
    }

    /**
     * @param string $fileKey
     * @return PromiseInterface
     */
    public function deleteImageObject(string $fileKey): PromiseInterface
    {
        return $this->awsS3Client->deleteObjectAsync([ 'Bucket' => $this->awsBucketImage, 'Key' => $fileKey]);
    }

    /**
     * @param string $fileKey
     * @return PromiseInterface
     */
    public function deleteOriginalVideoObject(string $fileKey): PromiseInterface
    {
        return $this->awsS3Client->deleteObjectAsync([ 'Bucket' => $this->awsBucketVideo, 'Key' => $fileKey]);
    }

    /**
     * @param string $transcodedUrlHDKey
     * @param string|null $transcodedUrlFullHDKey
     * @return array
     */
    public function deleteTranscodedVideoObject(string $transcodedUrlHDKey, ?string $transcodedUrlFullHDKey): array
    {
        $promises = [];
        $promises[] = $this->awsS3Client->deleteObjectAsync([ 'Bucket' => $this->awsBucketTranscoded, 'Key' => 'tc/'.$transcodedUrlHDKey]);

        if ($transcodedUrlFullHDKey) {
            $promises[] = $this->awsS3Client->deleteObjectAsync([ 'Bucket' => $this->awsBucketTranscoded, 'Key' => 'tc/'.$transcodedUrlFullHDKey]);
        }

        return $promises;
    }
}
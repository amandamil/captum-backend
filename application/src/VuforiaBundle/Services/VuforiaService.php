<?php

namespace VuforiaBundle\Services;

use Exception;
use ExperienceBundle\Entity\Experience;
use GuzzleHttp\Client as guzzleClient;
use GuzzleHttp\Psr7\Request as guzzleRequest;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ClientException;

class VuforiaService
{
    private $serverAccessKey;
    private $serverSecretKey;
    private $rootDir;
    private $client;
    private $url = "https://vws.vuforia.com";

    private function createRequest($options) {
        $method = $options['method'];

        $hexDigest = "d41d8cd98f00b204e9800998ecf8427e";

        $contentType = "";

        // Not all requests will define a content-type
        if( isset( $options['contentType'] ))
            $contentType = $options['contentType'];

        if ( $method == 'GET' || $method == 'DELETE' ) {
            // Do nothing because the strings are already set correctly
        } else if ( $method == 'POST' || $method == 'PUT' ) {
            // If this is a POST or PUT the request should have a request body
            $hexDigest = md5($options['body'] , false);
        }

        $toDigest = $method . "\n" . $hexDigest . "\n" . $contentType . "\n" . $options['date'] . "\n" . $options['uri'];

        $shaHashed = "";

        try {
            // the SHA1 hash needs to be transformed from hexidecimal to Base64
            $shaHashed = $this->hexToBase64( hash_hmac("sha1", $toDigest , $this->serverSecretKey) );

        } catch ( Exception $e) {
            $e->getMessage();
        }
        $requestHeaders = [];
        $requestHeaders['Date'] = $options['date'];
        $requestHeaders['Content-Type'] = $contentType;
        $requestHeaders['Authorization'] = "VWS ".$this->serverAccessKey.':'.$shaHashed;
        if($method == 'GET' || $method == 'DELETE')
        {
            return new guzzleRequest($method, $this->url.$options['uri'], $requestHeaders);
        }
        return new guzzleRequest($method, $this->url.$options['uri'], $requestHeaders, $options['body']);
    }


    private function hexToBase64($hex){

        $return = "";

        foreach(str_split($hex, 2) as $pair){

            $return .= chr(hexdec($pair));

        }

        return base64_encode($return);
    }

    public function __construct($accessKey, $secretKey, $rootDir)
    {
        $this->serverAccessKey = $accessKey;
        $this->serverSecretKey = $secretKey;
        $this->rootDir = $rootDir;

        $this->client = new guzzleClient([
            // Base URI is used with relative requests
            'base_uri' => $this->url,
        ]);
    }

    /**
     * @param string $file
     * @param string $fileKey
     * @return mixed
     * @throws GuzzleException
     */
    public function createEmptyTarget(string $file, string $fileKey)
    {
        $date = new \DateTime("now", new \DateTimeZone("GMT"));
        $date = $date->format("D, d M Y H:i:s") . " GMT";

        $body = json_encode([
            'width' => 0.247,
            'name' => $fileKey,
            'image' => base64_encode($file),
            'active_flag' => 0,
        ]);

        $request = $this->createRequest([
            'method'=> 'POST',
            'contentType' => 'application/json',
            'body' => $body,
            'date' => $date,
            'uri' => '/targets',
        ]);

        try {
            $response = $this->client->send($request);
            return  json_decode($response->getBody()->getContents());
        } catch (ClientException $e) {
            return json_decode($e->getResponse()->getBody()->getContents());
        }
    }

    /**
     * @param string $targetId
     * @return mixed
     * @throws GuzzleException
     */
    public function checkDuplicates(string $targetId)
    {
        $date = new \DateTime("now", new \DateTimeZone("GMT"));
        $date = $date->format("D, d M Y H:i:s") . " GMT";

        $request = $this->createRequest([
            'method'=> 'GET',
            'date' => $date,
            'uri' => '/duplicates/'.$targetId,
        ]);

        try {
            $response = $this->client->send($request);
            return  json_decode($response->getBody()->getContents());
        } catch (ClientException $e) {
            return json_decode($e->getResponse()->getBody()->getContents());
        }
    }

    /**
     * @param Experience $experience
     * @param string     $file
     * @param string     $fileKey
     * @return mixed
     * @throws GuzzleException
     */
    public function addNewTarget(Experience $experience, string $file, string $fileKey)
    {
        $metadata = json_encode([
            'experienceId' => $experience->getId(),
            'status' => $experience->getStatus(),
        ]);

        $date = new \DateTime("now", new \DateTimeZone("GMT"));
        $date = $date->format("D, d M Y H:i:s") . " GMT";

        $body = json_encode([
            'width' => 0.247,
            'name' => $fileKey,
            'image' => base64_encode($file),
            'application_metadata' => base64_encode($metadata),
            'active_flag' => 1
        ]);

        $request = $this->createRequest([
            'method'=> 'POST',
            'contentType' => 'application/json',
            'body' => $body,
            'date' => $date,
            'uri' => '/targets',
        ]);

        try{
            $response = $this->client->send($request);
            return  json_decode($response->getBody()->getContents());
        } catch (ClientException $e) {
            return json_decode($e->getResponse()->getBody()->getContents());
        }
    }

    /**
     * @param string $targetId
     * @return mixed
     * @throws GuzzleException
     */
    public function deleteTarget(string $targetId) {
        $date = new \DateTime("now", new \DateTimeZone("GMT"));
        $date = $date->format("D, d M Y H:i:s") . " GMT";

        $request = $this->createRequest([
            'method'=> 'DELETE',
            'date' => $date,
            'uri' => '/targets/'.$targetId,
        ]);

        try {
            $response = $this->client->send($request);
            return json_decode($response->getBody()->getContents());
        } catch (ClientException $e) {
            return json_decode($e->getResponse()->getBody()->getContents());
        }
    }

    /**
     * @param Experience $experience
     * @param int        $flag
     * @return mixed
     * @throws GuzzleException
     */
    public function updateTarget(Experience $experience, int $flag)
    {
        $metadata = json_encode([
            'experienceId' => $experience->getId(),
            'status' => $flag,
        ]);

        $date = new \DateTime("now", new \DateTimeZone("GMT"));
        $date = $date->format("D, d M Y H:i:s") . " GMT";

        $body = json_encode([
            'application_metadata' => base64_encode($metadata),
            'active_flag' => $flag
        ]);

        $request = $this->createRequest([
            'method'=> 'PUT',
            'contentType' => 'application/json',
            'body' => $body,
            'date' => $date,
            'uri' => '/targets/'.$experience->getTargetId(),
        ]);

        try{
            $response = $this->client->send($request);
            return  json_decode($response->getBody()->getContents());
        } catch (ClientException $e) {
            return json_decode($e->getResponse()->getBody()->getContents());
        }
    }

    /**
     * @param Experience $experience
     * @param int $status
     * @return mixed
     * @throws GuzzleException
     */
    public function updateTargetStatus(Experience $experience, int $status) {
        $date = new \DateTime("now", new \DateTimeZone("GMT"));
        $date = $date->format("D, d M Y H:i:s") . " GMT";

        $metadata = json_encode([
            'experienceId' => $experience->getId(),
            'status' => $status
        ]);
        if($status === Experience::EXPERIENCE_DELETED) {
            $body = json_encode([
                'application_metadata' => base64_encode($metadata),
                'active_flag' => 0
            ]);

        } else {
            $body = json_encode([
                'application_metadata' => base64_encode($metadata),
            ]);
        }
        $request = $this->createRequest([
            'method'=> 'PUT',
            'contentType' => 'application/json',
            'body' => $body,
            'date' => $date,
            'uri' => '/targets/'.$experience->getTargetId(),
        ]);

        try{
            $response = $this->client->send($request);
            return  json_decode($response->getBody()->getContents());
        } catch (ClientException $e) {
            return json_decode($e->getResponse()->getBody()->getContents());
        }
    }

    /**
     * @param $experience
     * @return mixed
     * @throws GuzzleException
     */
    public function getTargetImage($experience) {
        $date = new \DateTime("now", new \DateTimeZone("GMT"));
        $date = $date->format("D, d M Y H:i:s") . " GMT";
        $request = $this->createRequest([
            'method'=> 'GET',
            'date' => $date,
            'uri' => '/targets/'.$experience->getTargetId(),
        ]);

        try{
            $response = $this->client->send($request);
            return  json_decode($response->getBody()->getContents());
        } catch (ClientException $e) {
            return json_decode($e->getResponse()->getBody()->getContents());
        }
    }

    /**
     * To get a summary of a specific image in a database
     *
     * @param Experience $experience
     * @return mixed
     * @throws GuzzleException
     */
    public function getTargetSummaryReport(Experience $experience)
    {
        $date = new \DateTime("now", new \DateTimeZone("GMT"));
        $date = $date->format("D, d M Y H:i:s") . " GMT";

        $request = $this->createRequest([
            'method'=> 'GET',
            'date' => $date,
            'uri' => '/summary/'.$experience->getTargetId(),
        ]);

        try{
            $response = $this->client->send($request);
            return  json_decode($response->getBody()->getContents());
        } catch (ClientException $e) {
            return json_decode($e->getResponse()->getBody()->getContents());
        }
    }
}
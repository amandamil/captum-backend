<?php

namespace ExperienceBundle\Model;

use FFMpeg\FFProbe\DataMapping\Stream;

/**
 * Class VideoProcessingResult
 * @package ExperienceBundle\Model
 */
class VideoProcessingResult implements ProcessingResultInterface
{
    /** @var bool $success */
    private $success = false;

    /** @var string|null $message */
    private $message;

    /** @var mixed $video */
    private $video;

    /** @var string|null $extension */
    private $extension;

    /** @var Stream|null $ffmpegStream */
    private $ffmpegStream;

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @param bool $success
     */
    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    /**
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * @param string|null $message
     */
    public function setMessage(?string $message = null): void
    {
        $this->message = $message;
    }

    /**
     * @return mixed
     */
    public function getVideo()
    {
        return $this->video;
    }

    /**
     * @param mixed $video
     */
    public function setVideo($video): void
    {
        $this->video = $video;
    }

    /**
     * @return string|null
     */
    public function getExtension(): ?string
    {
        return $this->extension;
    }

    /**
     * @param string|null $extension
     */
    public function setExtension(?string $extension = null): void
    {
        $this->extension = $extension;
    }

    /**
     * @return Stream|null
     */
    public function getFfmpegStream(): ?Stream
    {
        return $this->ffmpegStream;
    }

    /**
     * @param Stream|null $ffmpegStream
     */
    public function setFfmpegStream(?Stream $ffmpegStream = null): void
    {
        $this->ffmpegStream = $ffmpegStream;
    }
}
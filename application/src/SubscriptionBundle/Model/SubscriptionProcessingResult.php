<?php


namespace SubscriptionBundle\Model;

use ExperienceBundle\Model\ProcessingResultInterface;

/**
 * Class SubscriptionProcessingResult
 * @package SubscriptionBundle\Model
 */
class SubscriptionProcessingResult implements ProcessingResultInterface
{
    /** @var bool $success */
    private $success = false;

    /** @var string|null $message */
    private $message;

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
}
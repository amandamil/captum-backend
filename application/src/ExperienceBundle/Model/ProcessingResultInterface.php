<?php

namespace ExperienceBundle\Model;

/**
 * Interface ProcessingResultInterface
 * @package ExperienceBundle\Model
 */
interface ProcessingResultInterface
{
    /**
     * @return bool
     */
    public function isSuccess(): bool;

    /**
     * @param bool $success
     */
    public function setSuccess(bool $success): void;

    /**
     * @return string|null
     */
    public function getMessage(): ?string;

    /**
     * @param string|null $message
     */
    public function setMessage(string $message = null): void;
}
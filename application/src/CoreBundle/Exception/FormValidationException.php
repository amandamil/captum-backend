<?php

namespace CoreBundle\Exception;

use Symfony\Component\Form\FormError;

/**
 * Class FormValidationException.
 */
class FormValidationException extends \RuntimeException
{
    /**
     * @var string
     */
    protected $error;

    /**
     * @var null|string
     */
    protected $path;

    /**
     * FormValidationException constructor.
     *
     * @param string $error
     * @param string $path
     */
    public function __construct(string $error, $path = null)
    {
        $this->error = $error;
        $this->path = $path;

        parent::__construct('Form controller validation exception');
    }

    /**
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }
}

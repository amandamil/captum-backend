<?php


namespace CoreBundle\Exception;

use Symfony\Component\Form\FormError;

class FormValidationAllException extends \RuntimeException
{
    /**
     * @var array
     */
    protected $errors;

    /**
     * FormValidationAllException constructor.
     *
     * @param array $errors
     */
    public function __construct(array $errors)
    {
        $this->errors = $errors;

        parent::__construct('Form controller validation exception');
    }

    /**
     * @return FormError[]
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
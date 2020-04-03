<?php

namespace UserApiBundle\Model;

use Symfony\Component\Validator\Constraints as Assert;

class ChangeForgotPassword
{
    /**
     * @var string
     *
     * @Assert\NotBlank(message="New password should not be blank")
     * @Assert\Regex(
     *     pattern="/^(\w|[!@#$%^*()_+\-=\[\]{}\\,.\/?~`]){6,50}$/",
     *     message="Password should have only letters, numbers and symbols(e.g. @, ., #, -, _) and it's length should be from 6 to 50"
     * )
     *
     */
    private $newPassword;

    /**
     * @return mixed
     */
    public function getNewPassword()
    {
        return $this->newPassword;
    }

    /**
     * @param mixed $newPassword
     */
    public function setNewPassword($newPassword): void
    {
        $this->newPassword = $newPassword;
    }
}
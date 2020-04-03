<?php

namespace UserApiBundle\Model;

use Symfony\Component\Security\Core\Validator\Constraints as SecurityAssert;
use Symfony\Component\Validator\Constraints as Assert;

class ChangePassword
{
    /**
     * @var string
     * @SecurityAssert\UserPassword(
     *     message = "Wrong value for your current password"
     * )
     */
    private $oldPassword;

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
     * @return mixed
     */
    public function getOldPassword()
    {
        return $this->oldPassword;
    }

    /**
     * @param mixed $newPassword
     */
    public function setNewPassword($newPassword): void
    {
        $this->newPassword = $newPassword;
    }

    /**
     * @param mixed $oldPassword
     */
    public function setOldPassword($oldPassword): void
    {
        $this->oldPassword = $oldPassword;
    }
}
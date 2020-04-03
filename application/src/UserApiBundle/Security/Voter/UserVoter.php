<?php

namespace UserApiBundle\Security\Voter;

use CoreBundle\Security\Voter\BaseVoter;
use Symfony\Component\Security\Core\{ Authentication\Token\TokenInterface, Authorization\Voter\Voter };
use UserApiBundle\Entity\User;

/**
 * Class UserVoter
 * @package UserApiBundle\Security\Voter
 */
class UserVoter extends Voter
{
    const ACCESS_CHANGE_PASSWORD = 'changePassword';
    const ACCESS_FILL_BALANCE = 'fillBalance';
    const ACCESS_DEVICE = 'handleDevice';

    /**
     * @param string $attribute
     * @param mixed $subject
     * @return bool|void
     */
    protected function supports($attribute, $subject)
    {
        if (!in_array($attribute, [
            BaseVoter::ACCESS_CREATE,
            BaseVoter::ACCESS_EDIT,
            BaseVoter::ACCESS_VIEW,
            BaseVoter::ACCESS_LIST,
            self::ACCESS_CHANGE_PASSWORD,
            self::ACCESS_FILL_BALANCE,
            self::ACCESS_DEVICE,
        ])) {
            return false;
        }

        return $subject instanceof User;
    }

    /**
     * {@inheritdoc}
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $methodName = 'can'.ucfirst($attribute);
        if (!method_exists($this, $methodName)) {
            return false;
        }

        return $this->{'can'.ucfirst($attribute)}($subject, $token);
    }

    /**
     * @param $user
     * @param TokenInterface $token
     * @return bool
     */
    private function canView($user, TokenInterface $token)
    {

        if ($token->getUser() instanceof User) {
            return true;
        }

        return false;
    }

    /**
     * @param mixed $user
     * @param TokenInterface $token
     * @return bool
     */
    private function canEdit($user, TokenInterface $token)
    {
        if ($token->getUser() instanceof User) {
            return true;
        }

        return false;
    }

    /**
     * @param mixed $user
     * @param TokenInterface $token
     * @return bool
     */
    private function canFillBalance($user, TokenInterface $token)
    {

        if ($user instanceof User && !$user->isExample()) {
            return true;
        }

        return false;
    }

    /**
     * @param $user
     * @param TokenInterface $token
     * @return bool
     */
    private function canChangePassword($user, TokenInterface $token)
    {
        if ($user instanceof User && !$user->isExample()) {
            return true;
        }

        return false;
    }

    private function canHandleDevice($user, TokenInterface $token)
    {
        if ($token->getUser() instanceof User) {
            return true;
        }

        return false;
    }
}

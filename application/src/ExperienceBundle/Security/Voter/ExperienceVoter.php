<?php

namespace ExperienceBundle\Security\Voter;

use CoreBundle\Security\Voter\BaseVoter;
use ExperienceBundle\Entity\Experience;
use Symfony\Component\Security\Core\{ Authentication\Token\TokenInterface, Authorization\Voter\Voter };
use UserApiBundle\Entity\User;

/**
 * Class ExperienceVoter
 * @package ExperienceBundle\Security\Voter
 */
class ExperienceVoter extends Voter
{
    const ACCESS_EDIT_VIDEO = 'editVideo';

    /**
     * @param string $attribute
     * @param mixed $subject
     * @return bool
     */
    protected function supports($attribute, $subject)
    {
        if (!in_array($attribute, [
            BaseVoter::ACCESS_CREATE,
            BaseVoter::ACCESS_EDIT,
            BaseVoter::ACCESS_VIEW,
            BaseVoter::ACCESS_LIST,
            self::ACCESS_EDIT_VIDEO
        ])) {
            return false;
        }

        return $subject instanceof Experience;
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
     * @param Experience $subject
     * @param TokenInterface $token
     * @return bool
     */
    private function canCreate(Experience $subject, TokenInterface $token)
    {
        if ($token->getUser() instanceof User) {
            return true;
        }

        return false;
    }
    /**
     * @param $subject
     * @param TokenInterface $token
     * @return bool
     */
    private function canList($subject, TokenInterface $token)
    {
        if ($token->getUser() instanceof User) {
            return true;
        }

        return false;
    }

    /**
     * @param Experience $subject
     * @param TokenInterface $token
     * @return bool
     */
    private function canEdit(Experience $subject, TokenInterface $token)
    {
        if ($token->getUser() === $subject->getUser()) {
            return true;
        }

        return false;
    }

    /**
     * @param Experience $subject
     * @param TokenInterface $token
     * @return bool
     */
    private function canEditVideo(Experience $subject, TokenInterface $token)
    {
        if ($token->getUser() === $subject->getUser()
            && in_array($subject->getStatus(), [Experience::EXPERIENCE_ACTIVE, Experience::EXPERIENCE_DISABLED])) {
            return true;
        }

        return false;
    }
}
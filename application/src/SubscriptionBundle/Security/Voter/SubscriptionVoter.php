<?php

namespace SubscriptionBundle\Security\Voter;

use CoreBundle\Security\Voter\BaseVoter;
use SubscriptionBundle\Entity\Subscription;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use UserApiBundle\Entity\User;

class SubscriptionVoter extends Voter
{
    const ACCESS_PAYMENT_UPDATE = 'paymentUpdate';

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
            BaseVoter::ACCESS_CANCEL,
            self::ACCESS_PAYMENT_UPDATE,
        ])) {
            return false;
        }

        return $subject instanceof Subscription;
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
     * @param Subscription $subject
     * @param TokenInterface $token
     * @return bool
     */
    private function canCreate(Subscription $subject, TokenInterface $token)
    {
        $user = $token->getUser();
        if ($user instanceof User && !$user->isExample()) {
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
        $user = $token->getUser();
        if ($user instanceof User && !$user->isExample()) {
            return true;
        }

        return false;
    }

    /**
     * @param Subscription $subscription
     * @param TokenInterface $token
     * @return bool
     */
    private function canView(Subscription $subscription, TokenInterface $token)
    {
        $user = $token->getUser();
        if ($user instanceof User) {
            return true;
        }

        return false;
    }

    /**
     * @param Subscription $subscription
     * @param TokenInterface $token
     * @return bool
     * @throws \Exception
     */
    private function canEdit(Subscription $subscription, TokenInterface $token)
    {
        $user = $token->getUser();
        if ($user instanceof User && !$user->isExample()) {
            return true;
        }

        return false;
    }

    /**
     * @param Subscription $subscription
     * @param TokenInterface $token
     * @return bool
     */
    private function canCancel(Subscription $subscription, TokenInterface $token)
    {
        $user = $token->getUser();
        if ($user instanceof User && !$user->isExample()) {
            return true;
        }

        return false;
    }

    /**
     * @param Subscription $subscription
     * @param TokenInterface $token
     * @return bool
     */
    private function canPaymentUpdate(Subscription $subscription, TokenInterface $token)
    {
        $user = $token->getUser();
        if ($user instanceof User && !$user->isExample()) {
            return true;
        }

        return false;
    }
}
<?php

namespace CoreBundle\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class BaseVoter extends Voter
{
    const ACCESS_CREATE = 'create';
    const ACCESS_EDIT = 'edit';
    const ACCESS_VIEW = 'view';
    const ACCESS_LIST = 'list';
    const ACCESS_DELETE = 'delete';
    const ACCESS_CANCEL = 'cancel';

    /**
     * {@inheritdoc}
     */
    protected function supports($attribute, $subject)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        return false;
    }
}
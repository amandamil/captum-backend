<?php

namespace SubscriptionBundle\Repository;

use UserApiBundle\Entity\User;

/**
 * Class PackageRepository
 * @package SubscriptionBundle\Repository
 */
class PackageRepository extends \Doctrine\ORM\EntityRepository
{
    /**
     * @param User $user
     * @param int|null $packageId
     * @return \Doctrine\ORM\Query
     */
    public function getAvailablePackages(User $user, int $packageId = null)
    {
        $qb = $this->createQueryBuilder('package');

        if ($user->isTrialUsed()) {
            $qb->where($qb->expr()->neq('package.isTrial', ':trial'))
                ->setParameter('trial', true);

            if (!is_null($packageId)) {
                $qb->andWhere($qb->expr()->neq('package.id', ':currentPackage'))
                    ->setParameter('currentPackage', $packageId);
            }
        }

        return $qb->andWhere($qb->expr()->eq('package.isPublic', true))
            ->orderBy('package.price', 'ASC')->getQuery();
    }
}
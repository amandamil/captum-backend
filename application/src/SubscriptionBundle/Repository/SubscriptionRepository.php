<?php

namespace SubscriptionBundle\Repository;

use Doctrine\Common\Collections\Criteria;
use SubscriptionBundle\Entity\Subscription;
use function Doctrine\ORM\QueryBuilder;

/**
 * Class SubscriptionRepository
 * @package SubscriptionBundle\Repository
 */
class SubscriptionRepository extends \Doctrine\ORM\EntityRepository
{
    /**
     * @param int $subscriptionId
     * @param string $provider
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findSubscriptionForAutorenew(int $subscriptionId, string $provider)
    {
        $qb = $this->createQueryBuilder('s');

        return $qb->where($qb->expr()->eq('s.id',':id'))
            ->andWhere($qb->expr()->eq('s.providerType',':providerType'))
            ->andWhere($qb->expr()->in('s.status', ':statuses'))
            ->setParameters([
                'id' => $subscriptionId,
                'providerType' => $provider,
                'statuses' => [
                    Subscription::STATUS_ACTIVE,
                    Subscription::STATUS_CHARGED_UNSUCCESSFULLY,
                    Subscription::STATUS_PAST_DUE,
                ]
            ])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Criteria
     * @throws \Exception
     */
    static public function createActiveSubscriptionCriteria(): Criteria
    {
        return Criteria::create()
            ->andWhere(Criteria::expr()->eq('status', Subscription::STATUS_ACTIVE))
            ->andWhere(Criteria::expr()->gt('expiresAt', new \DateTime()));
    }

    /**
     * @return Criteria
     */
    static public function createActiveSubscriptionUpdatePaymentMethodCriteria(): Criteria
    {
        return Criteria::create()
            ->where(Criteria::expr()
            ->notIn('status', [Subscription::STATUS_EXPIRED, Subscription::STATUS_CANCELED]));
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function findPendingSubscriptions()
    {
        $qb = $this->createQueryBuilder('s');

        return $qb->where($qb->expr()->eq('s.status', ':status'))
            ->andWhere($qb->expr()->lt('s.createdAt', ':deleteTime'))
            ->setParameters([
                'status' => Subscription::STATUS_PENDING,
                'deleteTime' => (new \DateTime())->sub(new \DateInterval('PT1H'))
            ])
            ->getQuery()
            ->getResult();
    }
}
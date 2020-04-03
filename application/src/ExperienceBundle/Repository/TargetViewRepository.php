<?php

namespace ExperienceBundle\Repository;

use Doctrine\Common\Collections\Criteria;
use ExperienceBundle\Entity\Experience;
use \DateTime;
use SubscriptionBundle\Entity\Subscription;

/**
 * Class TargetViewRepository
 * @package ExperienceBundle\Repository
 */
class TargetViewRepository extends \Doctrine\ORM\EntityRepository
{
    /**
     * Get views for expr by date
     *
     * @param Experience $experience
     * @param DateTime $date
     * @param bool $isTrial
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getTargetViewsByDate(Experience $experience, DateTime $date, bool $isTrial)
    {
        $qb = $this->createQueryBuilder('tv');

        return $qb->where($qb->expr()->eq('tv.user', ':user'))
            ->andWhere($qb->expr()->eq('tv.experience', ':experience'))
            ->andWhere($qb->expr()->eq('tv.isTrial', ':isTrial'))
            ->andWhere('YEAR(tv.createdAt) = :year')
            ->andWhere('MONTH(tv.createdAt) = :month')
            ->andWhere('DAY(tv.createdAt) = :day')
            ->setParameter('user', $experience->getUser())
            ->setParameter('experience', $experience)
            ->setParameter('isTrial', $isTrial)
            ->setParameter('year', (int) $date->format('Y'))
            ->setParameter('month', (int) $date->format('m'))
            ->setParameter('day', (int) $date->format('d'))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param Experience $experience
     * @param boolean $isTrial
     * @return int|null
     */
    public function getTargetViewsExceptDate(Experience $experience, bool $isTrial)
    {
        $qb = $this->createQueryBuilder('tv');

        return $qb->select('SUM(tv.views) AS views')
            ->where($qb->expr()->eq('tv.user', ':user'))
            ->andWhere($qb->expr()->eq('tv.experience', ':experience'))
            ->andWhere($qb->expr()->eq('tv.isTrial', ':isTrial'))
            ->andWhere('DATE(tv.createdAt) <> CURRENT_DATE()')
            ->setParameter('user', $experience->getUser())
            ->setParameter('experience', $experience)
            ->setParameter('isTrial', $isTrial)
            ->groupBy('tv.user')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(4);
    }

    /**
     * @param Experience   $experience
     * @param Subscription $subscription
     * @return int|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getTargetViewsExceptDateByPeriod(Experience $experience, Subscription $subscription)
    {
        $qb = $this->createQueryBuilder('tv');

        $expiresAt = $subscription->getExpiresAt();
        $periodStarts = clone $expiresAt;

        if ($subscription->getPackage()->isTrial()) {
            $periodStarts->modify("-".$subscription->getPackage()->getExpiresInMonths()." month");
        } else {
            $periodStarts->modify("-".$subscription->getPackage()->getExpiresInMonths()." month");
        }

        return $qb->select('SUM(tv.views) AS views')
            ->where($qb->expr()->eq('tv.user', ':user'))
            ->andWhere($qb->expr()->eq('tv.experience', ':experience'))
            ->andWhere($qb->expr()->between('tv.createdAt', ':from', ':to'))
            ->andWhere('DATE(tv.createdAt) <> CURRENT_DATE()')
            ->setParameter('user', $experience->getUser())
            ->setParameter('experience', $experience)
            ->setParameter('from', $periodStarts)
            ->setParameter('to', $expiresAt)
            ->groupBy('tv.user')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(4);
    }

    /**
     * @param Experience $experience
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getTargetViewsTrial(Experience $experience)
    {
        $qb = $this->createQueryBuilder('tv');

        return $qb->select('SUM(tv.views) AS views')
            ->where($qb->expr()->eq('tv.user', ':user'))
            ->andWhere($qb->expr()->eq('tv.experience', ':experience'))
            ->andWhere($qb->expr()->eq('tv.isTrial', ':isTrial'))
            ->setParameter('user', $experience->getUser())
            ->setParameter('experience', $experience)
            ->setParameter('isTrial', true)
            ->groupBy('tv.user')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(4);
    }

    /**
     * @param \DateTime $periodStarts
     * @param \DateTime $expiresAt
     * @param bool $isTrial
     * @return Criteria
     */
    static public function createViewsNumberCriteria(\DateTime $periodStarts, \DateTime $expiresAt, bool $isTrial): Criteria
    {
        return Criteria::create()
            ->where(Criteria::expr()->eq('isTrial', $isTrial))
            ->andWhere(Criteria::expr()->gte('updatedAt', $periodStarts))
            ->andWhere(Criteria::expr()->lte('updatedAt', $expiresAt));
    }
}
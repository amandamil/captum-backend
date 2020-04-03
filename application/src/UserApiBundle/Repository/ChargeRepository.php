<?php

namespace UserApiBundle\Repository;

use Doctrine\Common\Collections\Criteria;
use UserApiBundle\Entity\Balance;
use UserApiBundle\Entity\Charge;

/**
 * Class ChargeRepository
 * @package UserApiBundle\Repository
 *
 * @method Charge|null find($id, $lockMode = null, $lockVersion = null)
 * @method Charge|null findOneBy(array $criteria, array $orderBy = null)
 * @method Charge[]    findAll()
 * @method Charge[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ChargeRepository extends \Doctrine\ORM\EntityRepository
{
    /**
     * @param Balance $balance
     * @param \DateTime $dateFrom
     * @param \DateTime $dateTo
     * @return Charge[]
     */
    public function findByBalanceRangeDate(Balance $balance, \DateTime $dateFrom, \DateTime $dateTo)
    {
        $qb = $this->createQueryBuilder('c');

        return $qb->where($qb->expr()->eq('c.balance', ':balance'))
            ->andWhere('c.createdAt BETWEEN :dateFrom AND :dateTo')
            ->setParameter('balance', $balance)
            ->setParameter('dateFrom', $dateFrom)
            ->setParameter('dateTo', $dateTo)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @param \DateTime $periodStarts
     * @param \DateTime $expiresAt
     * @return Criteria
     */
    static public function createSubscriptionPeriodChargesCriteria(\DateTime $periodStarts, \DateTime $expiresAt): Criteria
    {
        return Criteria::create()
            ->where(Criteria::expr()->gte('createdAt', $periodStarts))
            ->andWhere(Criteria::expr()->lte('createdAt', $expiresAt));
    }
}

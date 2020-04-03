<?php

namespace SubscriptionBundle\Repository;

use Doctrine\Common\Collections\Criteria;
use SubscriptionBundle\Entity\Transaction;
use UserApiBundle\Entity\User;

/**
 * Class TransactionRepository
 * @package SubscriptionBundle\Repository
 *
 * @method Transaction|null find($id, $lockMode = null, $lockVersion = null)
 * @method Transaction|null findOneBy(array $criteria, array $orderBy = null)
 * @method Transaction[]    findAll()
 * @method Transaction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransactionRepository extends \Doctrine\ORM\EntityRepository
{
    /**
     * @param User $user
     * @return Criteria
     */
    public static function createPurchaseHistoryCriteria(User $user): Criteria
    {
        $expr = Criteria::expr();
        return Criteria::create()
            ->where(
                $expr->andX(
                    $expr->eq('user', $user),
                    $expr->in('status',Transaction::SUCCESS_STATUSES)
                )
            )
            ->orderBy(['createdAt' => 'DESC']);
    }

    /**
     * @param User $user
     * @return Transaction|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findLastByUser(User $user)
    {
        $qb = $this->createQueryBuilder('t');

        return $qb->where($qb->expr()->eq('t.user', ':user'))
            ->setParameter('user', $user)
            ->orderBy('t.updatedAt','DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * @param User $user
     * @return mixed
     * @throws \Doctrine\ORM\Query\QueryException
     */
    public function getUserPurchaseHistory(User $user)
    {
        return $this->createQueryBuilder('t')
            ->addCriteria(self::createPurchaseHistoryCriteria($user))
            ->distinct()
            ->getQuery()
            ->execute();
    }
}

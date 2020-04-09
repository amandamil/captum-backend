<?php

namespace ExperienceBundle\Repository;


use Doctrine\Common\Collections\Criteria;
use ExperienceBundle\Entity\Experience;
use UserApiBundle\Entity\User;

/**
 * ExperienceRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ExperienceRepository extends \Doctrine\ORM\EntityRepository
{
    public function findByUser(User $user, $filter)
    {
        $qb = $this->createQueryBuilder('exp');

        return $qb->where( $qb->expr()->eq('exp.user',':user'))
            ->andWhere($filter ? $qb->expr()->in('exp.status', $filter) : $qb->expr()->neq('exp.status', Experience::EXPERIENCE_DELETED))
            ->orderBy('exp.status', 'ASC')
            ->addOrderBy('exp.createdAt','DESC')
            ->setParameter('user', $user)
            ->getQuery();
    }

    /**
     * @param User $user
     * @param int $maxResults
     * @return mixed
     */
    public function findUserDeactivatedExperiences(User $user, int $maxResults)
    {
        $qb = $this->createQueryBuilder('exp');

        return $qb->where($qb->expr()->eq('exp.user',':user'))
            ->andWhere($qb->expr()->eq('exp.isLastUsed',':isLastUsed'))
            ->addOrderBy('exp.createdAt','DESC')
            ->setParameter('user', $user)
            ->setParameter('isLastUsed', true)
            ->getQuery()
            ->setMaxResults($maxResults)
            ->getResult();
    }

    /**
     * @return mixed
     */
    public function getExamples()
    {
        $qb = $this->createQueryBuilder('exp');

        return $qb->where($qb->expr()->eq('exp.isExample', ':isExample'))
            ->andWhere($qb->expr()->eq('exp.status', ':status'))
            ->setParameter('isExample', true)
            ->setParameter('status', Experience::EXPERIENCE_ACTIVE)
            ->addOrderBy('exp.createdAt','DESC')
            ->getQuery();
    }

    /**
     * @param int $currentExperienceId
     * @return Criteria
     */
    static public function createActiveWithoutCurrentCountCriteria(int $currentExperienceId): Criteria
    {
        return Criteria::create()
            ->andWhere(Criteria::expr()->neq('id', $currentExperienceId))
            ->andWhere(Criteria::expr()->in('status', [
                Experience::EXPERIENCE_PENDING,
                Experience::EXPERIENCE_ACTIVE
            ]));
    }
}
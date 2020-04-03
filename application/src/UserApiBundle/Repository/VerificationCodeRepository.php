<?php

namespace UserApiBundle\Repository;

use UserApiBundle\Model\VerificationCodeStatus;
use UserApiBundle\Model\VerificationCodeType;

/**
 * Class VerificationCodeRepository
 * @package UserApiBundle\Repository
 */
class VerificationCodeRepository extends \Doctrine\ORM\EntityRepository
{
    /**
     * @param string $code
     * @param string $email
     * @param string $type
     * @param mixed $status
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findActualByCodeAndEmail($code, $email, $type = VerificationCodeType::REGISTRATION, $status = VerificationCodeStatus::PENDING)
    {
        $qb = $this->createQueryBuilder('vc');

        return $qb->where($qb->expr()->eq('vc.code',':code'))
            ->andWhere($qb->expr()->eq('vc.email', ':email'))
            ->andWhere($qb->expr()->eq('vc.used', ':used'))
            ->andWhere($qb->expr()->in('vc.status', ':status'))
            ->andWhere($qb->expr()->eq('vc.type', ':type'))
            ->setParameters([
                'code' => $code,
                'email' => $email,
                'used' => false,
                'status' => $status,
                'type' => $type
            ])
            ->orderBy('vc.sentAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
<?php

namespace UserApiBundle\Repository;

use UserApiBundle\Entity\Client;

/**
 * Class ClientRepository
 * @package UserApiBundle\Repository
 *
 * @method Client|null find($id, $lockMode = null, $lockVersion = null)
 * @method Client|null findOneBy(array $criteria, array $orderBy = null)
 * @method Client[]    findAll()
 * @method Client[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientRepository extends \Doctrine\ORM\EntityRepository
{
    /**
     * @param string|null $publicId
     * @return Client|null
     */
    public function findClientByPublicId(?string $publicId): ?Client
    {
        if (false === $pos = mb_strpos($publicId, '_')) {
            return null;
        }

        $id = mb_substr($publicId, 0, $pos);
        $randomId = mb_substr($publicId, $pos + 1);

        return $this->findOneBy([
            'id' => $id,
            'randomId' => $randomId,
        ]);
    }
}
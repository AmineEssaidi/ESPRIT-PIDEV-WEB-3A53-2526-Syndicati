<?php

namespace App\Repository\FaceCred;

use App\Entity\FaceCred\FaceCredential;
use App\Entity\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FaceCredential>
 */
class FaceCredentialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FaceCredential::class);
    }

    public function save(FaceCredential $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FaceCredential $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find an active face credential for a specific user and device.
     */
    public function findActiveForUserAndDevice(User $user, string $deviceId): ?FaceCredential
    {
        return $this->createQueryBuilder('f')
            ->where('f.user = :user')
            ->andWhere('f.deviceId = :deviceId')
            ->andWhere('f.flag = :flag')
            ->setParameter('user', $user)
            ->setParameter('deviceId', $deviceId)
            ->setParameter('flag', 'active')
            ->getQuery()
            ->getOneOrNullResult();
    }
}

<?php

namespace App\Repository\Log;

use App\Entity\Log\AppEventLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AppEventLog>
 */
class AppEventLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppEventLog::class);
    }

    /**
     * @return AppEventLog[] Returns an array of AppEventLog objects
     */
    public function findByEntityType(string $type, int $id): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.entity_type = :val')
            ->andWhere('a.entity_id = :id')
            ->setParameter('val', $type)
            ->setParameter('id', $id)
            ->orderBy('a.created_at', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findLatestByUser(int $userId, int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.user', 'u')
            ->andWhere('u.id_user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('a.created_at', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }
}

<?php

namespace App\Repository\Syndicat;

use App\Entity\Syndicat\Reponse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reponse>
 */
class ReponseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reponse::class);
    }

    public function findByReclamationId(int $reclamationId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.reclamation = :val')
            ->setParameter('val', $reclamationId)
            ->orderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

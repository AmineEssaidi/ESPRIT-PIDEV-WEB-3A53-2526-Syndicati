<?php

namespace App\Repository\Evenement;

use App\Entity\Evenement\Evenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Evenement>
 */
class EvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenement::class);
    }

    /**
     * @return Evenement[]
     */
    public function findAllWithUser(): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.user', 'u')
            ->addSelect('u')
            ->orderBy('e.date_event', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Evenement[]
     */
    public function findUpcomingForHome(int $limit = 3): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.user', 'u')
            ->addSelect('u')
            ->andWhere('e.date_event >= :now')
            ->andWhere('e.statut_event != :cancelled')
            ->setParameter('now', new \DateTimeImmutable('today'))
            ->setParameter('cancelled', 'annule')
            ->orderBy('e.date_event', 'ASC')
            ->addOrderBy('e.edited_at', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

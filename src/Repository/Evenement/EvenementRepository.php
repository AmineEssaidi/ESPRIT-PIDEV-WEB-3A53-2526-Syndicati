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

    public function NTotalEvenements()
    {
        return $this->createQueryBuilder('e')
            ->select('count(e.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function EvennementsProchains()
    {
         return $this->createQueryBuilder('e')->
            orderBy('e.date_event', 'DESC')->
            setMaxResults(3)->
            getQuery()->
            getResult();      
  }
}

<?php

namespace App\Repository\Residence;

use App\Entity\Residence\Appartement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Appartement>
 *
 * @method Appartement|null find($id, $lockMode = null, $lockVersion = null)
 * @method Appartement|null findOneBy(array $criteria, array $orderBy = null)
 * @method Appartement[]    findAll()
 * @method Appartement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AppartementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appartement::class);
    }

    public function add(Appartement $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Appartement $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function NTotalAppartements()
    {
        return $this->createQueryBuilder('a')
            ->select('count(a.idApp)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function SommeAchatAppartements()
    {
        return $this->createQueryBuilder('a')
            ->select('sum(a.prix_vente)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function SommeLocationAppartements()
    {
        return $this->createQueryBuilder('a')
            ->select('sum(a.prix_location)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}

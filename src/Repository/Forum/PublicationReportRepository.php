<?php

namespace App\Repository\Forum;

use App\Entity\Forum\PublicationReport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PublicationReport>
 *
 * @method PublicationReport|null find($id, $lockMode = null, $lockVersion = null)
 * @method PublicationReport|null findOneBy(array $criteria, array $orderBy = null)
 * @method PublicationReport[]    findAll()
 * @method PublicationReport[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PublicationReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PublicationReport::class);
    }

    public function add(PublicationReport $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PublicationReport $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

<?php

namespace App\Repository\Forum;

use App\Entity\Forum\CommentReport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommentReport>
 *
 * @method CommentReport|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommentReport|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommentReport[]    findAll()
 * @method CommentReport[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommentReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommentReport::class);
    }

    public function add(CommentReport $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CommentReport $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

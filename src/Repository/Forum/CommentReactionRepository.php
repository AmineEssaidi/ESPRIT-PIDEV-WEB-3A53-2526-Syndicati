<?php

namespace App\Repository\Forum;

use App\Entity\Forum\CommentReaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommentReaction>
 *
 * @method CommentReaction|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommentReaction|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommentReaction[]    findAll()
 * @method CommentReaction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommentReactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommentReaction::class);
    }

    public function add(CommentReaction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CommentReaction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

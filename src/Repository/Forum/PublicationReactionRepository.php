<?php

namespace App\Repository\Forum;

use App\Entity\Forum\PublicationReaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PublicationReaction>
 *
 * @method PublicationReaction|null find($id, $lockMode = null, $lockVersion = null)
 * @method PublicationReaction|null findOneBy(array $criteria, array $orderBy = null)
 * @method PublicationReaction[]    findAll()
 * @method PublicationReaction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PublicationReactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PublicationReaction::class);
    }

    public function add(PublicationReaction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PublicationReaction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

<?php

namespace App\Repository\Forum;

use App\Entity\Forum\Reaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reaction>
 *
 * @method Reaction|null find($id, $lockMode = null, $lockVersion = null)
 * @method Reaction|null findOneBy(array $criteria, array $orderBy = null)
 * @method Reaction[]    findAll()
 * @method Reaction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reaction::class);
    }

    public function add(Reaction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Reaction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    //    /**
//     * @return Reaction[] Returns an array of Reaction objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('r.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

    //    public function findOneBySomeField($value): ?Reaction
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
    public function countByPublicationAndKind(int $publicationId, string $kind): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('count(r.id_reaction)')
            ->where('r.publication = :pubId')
            ->andWhere('r.kind = :kind')
            ->setParameter('pubId', $publicationId)
            ->setParameter('kind', $kind)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByCommentAndKind(int $commentId, string $kind): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('count(r.id_reaction)')
            ->where('r.commentaire = :commId')
            ->andWhere('r.kind = :kind')
            ->setParameter('commId', $commentId)
            ->setParameter('kind', $kind)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Reaction[]
     */
    public function findByCommentAndUser($comment, $user): array
    {
        return $this->findBy([
            'commentaire' => $comment,
            'user' => $user
        ]);
    }
}

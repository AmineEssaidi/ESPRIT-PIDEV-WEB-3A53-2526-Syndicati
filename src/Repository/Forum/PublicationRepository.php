<?php

namespace App\Repository\Forum;

use App\Entity\Forum\Publication;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Publication>
 *
 * @method Publication|null find($id, $lockMode = null, $lockVersion = null)
 * @method Publication|null findOneBy(array $criteria, array $orderBy = null)
 * @method Publication[]    findAll()
 * @method Publication[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PublicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Publication::class);
    }

    public function add(Publication $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Publication $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    //    /**
//     * @return Publication[] Returns an array of Publication objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

    public function findAllLatest(?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->addSelect('u')
            ->orderBy('p.date_creation_pub', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<int, array{post: Publication, prof: \App\Entity\Profile\Profile|null}>
     */
    public function findLatestWithProfiles(int $limit = 3, bool $generalOnly = false): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->addSelect('u')
            ->orderBy('p.date_creation_pub', 'DESC')
            ->setMaxResults($limit);

        if ($generalOnly) {
            $qb->andWhere('p.categorie_pub != :announcement')
                ->setParameter('announcement', 'Announcement');
        }

        $publications = $qb->getQuery()->getResult();

        if (empty($publications)) {
            return [];
        }

        $userIds = array_map(function ($p) {
            return $p->getUser()->getIdUser();
        }, $publications);

        $profiles = $this->getEntityManager()
            ->getRepository(\App\Entity\Profile\Profile::class)
            ->findBy(['user' => $userIds]);

        $profileMap = [];
        foreach ($profiles as $prof) {
            $profileMap[$prof->getUser()->getIdUser()] = $prof;
        }

        $final = [];
        foreach ($publications as $post) {
            $final[] = [
                'post' => $post,
                'prof' => $profileMap[$post->getUser()->getIdUser()] ?? null
            ];
        }

        return $final;
    }

    public function findByCategory(?string $category, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->addSelect('u')
            ->orderBy('p.date_creation_pub', 'DESC');

        if ($category === 'Announcement') {
            $qb->andWhere('p.categorie_pub = :cat')
                ->setParameter('cat', 'Announcement');
        } elseif ($category === 'General') {
            $qb->andWhere('p.categorie_pub != :cat')
                ->setParameter('cat', 'Announcement');
        }

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }
}

<?php

namespace App\Repository\Evenement;

use App\Entity\Evenement\Evenement;
use App\Entity\Evenement\Participation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Participation>
 *
 * @method Participation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Participation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Participation[]    findAll()
 * @method Participation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ParticipationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Participation::class);
    }

    /**
     * @return Participation[]
     */
    public function findByUserWithEvent(object $user): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.evenement', 'e')
            ->addSelect('e')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.date_participation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function deleteByEvenement(Evenement $evenement): int
    {
        return $this->createQueryBuilder('p')
            ->delete()
            ->where('p.evenement = :evenement')
            ->setParameter('evenement', $evenement)
            ->getQuery()
            ->execute();
    }
}

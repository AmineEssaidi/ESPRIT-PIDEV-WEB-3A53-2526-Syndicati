<?php

namespace App\Repository\Residence;

use App\Entity\Residence\Appartement;
use App\Entity\Residence\Review;
use App\Entity\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    public function findOneByUserAndAppartement(User $user, Appartement $appartement): ?Review
    {
        return $this->findOneBy([
            'user' => $user,
            'appartement' => $appartement,
        ]);
    }

    /**
     * @return array<int, array{apartment_id: int, average_score: float, review_count: int}>
     */
    public function getApartmentScoreSummary(): array
    {
        return $this->createQueryBuilder('r')
            ->select('IDENTITY(r.appartement) AS apartment_id')
            ->addSelect('AVG(r.score) AS average_score')
            ->addSelect('COUNT(r.idReview) AS review_count')
            ->groupBy('r.appartement')
            ->getQuery()
            ->getArrayResult();
    }
}

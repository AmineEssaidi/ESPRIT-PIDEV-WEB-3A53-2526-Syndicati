<?php

namespace App\Repository\Onboarding;

use App\Entity\Onboarding\Onboarding;
use App\Entity\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OnboardingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Onboarding::class);
    }

    public function findOneByUser(User $user): ?Onboarding
    {
        return $this->findOneBy(['user' => $user], ['id_onboarding' => 'DESC']);
    }

    public function findOneByUserId(int $userId): ?Onboarding
    {
        return $this->createQueryBuilder('o')
            ->innerJoin('o.user', 'u')
            ->andWhere('u.id_user = :id')
            ->setParameter('id', $userId)
            ->orderBy('o.id_onboarding', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

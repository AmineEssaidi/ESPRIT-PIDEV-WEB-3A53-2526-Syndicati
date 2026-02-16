<?php

namespace App\Repository\OAuth;

use App\Entity\OAuth\OAuth;
use App\Entity\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OAuthRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OAuth::class);
    }

    public function findOneByUser(User $user): ?OAuth
    {
        return $this->findOneBy(
            ['user' => $user],
            ['idOAuth' => 'DESC']
        );
    }

    public function findOneByUserId(int $userId): ?OAuth
    {
        return $this->createQueryBuilder('o')
            ->innerJoin('o.user', 'u')
            ->andWhere('u.id_user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('o.idOAuth', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Find one OAuth record (any user) – e.g. to use as fallback when MAILER_OAUTH_USER_ID is not set. */
    public function findOneAny(): ?OAuth
    {
        return $this->createQueryBuilder('o')
            ->orderBy('o.idOAuth', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Find OAuth record by user and optional provider scope (e.g. Gmail) */
    public function findOneByUserAndScope(User $user, ?string $scopeContains = null): ?OAuth
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.user = :user')
            ->setParameter('user', $user);

        if ($scopeContains !== null && $scopeContains !== '') {
            $qb->andWhere('o.scope LIKE :scope')
                ->setParameter('scope', '%' . $scopeContains . '%');
        }

        return $qb->orderBy('o.idOAuth', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

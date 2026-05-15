<?php

namespace App\Repository\OAuth;

use App\Entity\OAuth\OAuth;
use App\Entity\User\User;
use Doctrine\DBAL\Exception\TableNotFoundException;
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
        try {
            return $this->findOneBy(
                ['user' => $user],
                ['idOAuth' => 'DESC']
            );
        } catch (TableNotFoundException) {
            return null;
        }
    }

    public function findOneByUserId(int $userId): ?OAuth
    {
        try {
            return $this->createQueryBuilder('o')
                ->innerJoin('o.user', 'u')
                ->andWhere('u.id_user = :userId')
                ->setParameter('userId', $userId)
                ->orderBy('o.idOAuth', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        } catch (TableNotFoundException) {
            return null;
        }
    }

    /** Find one OAuth record (any user) – e.g. to use as fallback when MAILER_OAUTH_USER_ID is not set. */
    public function findOneAny(): ?OAuth
    {
        try {
            return $this->createQueryBuilder('o')
                ->orderBy('o.idOAuth', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        } catch (TableNotFoundException) {
            return null;
        }
    }

    /** Find OAuth record by user and optional provider scope (e.g. Gmail) */
    public function findOneByUserAndScope(User $user, ?string $scopeContains = null): ?OAuth
    {
        try {
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
        } catch (TableNotFoundException) {
            return null;
        }
    }
}

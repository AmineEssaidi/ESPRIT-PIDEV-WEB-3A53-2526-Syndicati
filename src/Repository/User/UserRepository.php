<?php
namespace App\Repository\User;

use App\Entity\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Find user by email (case-insensitive), for sign-in and OTP.
     */
    public function findOneByEmail(string $email): ?User
    {
        $qb = $this->createQueryBuilder('u')
            ->where('LOWER(u.email_user) = LOWER(:email)')
            ->setParameter('email', $email)
            ->setMaxResults(1);
        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Find user by auth code (for 2FA / email verification).
     * Optionally restrict to non-expired codes.
     */
    public function findOneByAuthCode(string $authCode, bool $onlyValid = true): ?User
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.authCode = :code')
            ->setParameter('code', $authCode);

        if ($onlyValid) {
            $qb->andWhere('u.authCode_expires_at > :now')
                ->setParameter('now', new \DateTime());
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Search users by first name or last name.
     */
    public function searchByName(string $query, int $excludeUserId, int $limit = 5): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.id_user != :excludeId')
            ->andWhere('LOWER(u.first_name) LIKE LOWER(:query) OR LOWER(u.last_name) LIKE LOWER(:query)')
            ->setParameter('excludeId', $excludeUserId)
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

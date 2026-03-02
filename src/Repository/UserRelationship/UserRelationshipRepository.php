<?php
namespace App\Repository\UserRelationship;

use App\Entity\UserRelationship\UserRelationship;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserRelationship>
 *
 * @method UserRelationship|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserRelationship|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserRelationship[]    findAll()
 * @method UserRelationship[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRelationshipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserRelationship::class);
    }

    /**
     * @return \App\Entity\User\User[]
     */
    public function findFriends(\App\Entity\User\User $user, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.status = :status')
            ->andWhere('r.user_first = :user OR r.user_second = :user')
            ->setParameter('status', 'FRIENDS')
            ->setParameter('user', $user)
            ->orderBy('r.updated_at', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        $relationships = $qb->getQuery()->getResult();
        $friends = [];
        foreach ($relationships as $rel) {
            if ($rel->getUserFirst()->getIdUser() === $user->getIdUser()) {
                $friends[] = $rel->getUserSecond();
            } else {
                $friends[] = $rel->getUserFirst();
            }
        }
        return $friends;
    }

    public function countFriends(\App\Entity\User\User $user): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.status = :status')
            ->andWhere('r.user_first = :user OR r.user_second = :user')
            ->setParameter('status', 'FRIENDS')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPendingRequests(\App\Entity\User\User $user): int
    {
        // PENDING_FIRST_SECOND: user_first sent to user_second
        // PENDING_SECOND_FIRST: user_second sent to user_first
        // So pending for $user are: 
        // (user_second = $user AND status = 'PENDING_FIRST_SECOND') OR (user_first = $user AND status = 'PENDING_SECOND_FIRST')

        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('(r.user_second = :user AND r.status = :p12) OR (r.user_first = :user AND r.status = :p21)')
            ->setParameter('user', $user)
            ->setParameter('p12', 'PENDING_FIRST_SECOND')
            ->setParameter('p21', 'PENDING_SECOND_FIRST')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Returns the UserRelationship entities for pending requests directed AT $user.
     * i.e. requests $user has not yet accepted.
     *
     * @return UserRelationship[]
     */
    public function findPendingRequestsFor(\App\Entity\User\User $user): array
    {
        return $this->createQueryBuilder('r')
            ->where('(r.user_second = :user AND r.status = :p12) OR (r.user_first = :user AND r.status = :p21)')
            ->setParameter('user', $user)
            ->setParameter('p12', 'PENDING_FIRST_SECOND')
            ->setParameter('p21', 'PENDING_SECOND_FIRST')
            ->orderBy('r.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findRelationship(\App\Entity\User\User $u1, \App\Entity\User\User $u2): ?UserRelationship
    {
        return $this->createQueryBuilder('r')
            ->where('(r.user_first = :u1 AND r.user_second = :u2) OR (r.user_first = :u2 AND r.user_second = :u1)')
            ->setParameter('u1', $u1)
            ->setParameter('u2', $u2)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

<?php
namespace App\Repository\Chat;

use App\Entity\Chat\Conversation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 *
 * @method Conversation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Conversation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Conversation[]    findAll()
 * @method Conversation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    /**
     * Finds all conversations where the user is a participant.
     * Ordered by the latest message's creation date (if possible) or conversation creation date.
     */
    public function findUserConversations(\App\Entity\User\User $user): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'p')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find a 1-on-1 conversation between two users.
     */
    public function findExistingConversation(\App\Entity\User\User $u1, \App\Entity\User\User $u2): ?Conversation
    {
        $qb = $this->createQueryBuilder('c');
        $qb->innerJoin('c.participants', 'p1')
            ->innerJoin('c.participants', 'p2')
            ->where('c.isGroup = false')
            ->andWhere('p1.user = :u1')
            ->andWhere('p2.user = :u2')
            ->setParameter('u1', $u1)
            ->setParameter('u2', $u2)
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }
}

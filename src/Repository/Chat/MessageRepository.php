<?php
namespace App\Repository\Chat;

use App\Entity\Chat\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 *
 * @method Message|null find($id, $lockMode = null, $lockVersion = null)
 * @method Message|null findOneBy(array $criteria, array $orderBy = null)
 * @method Message[]    findAll()
 * @method Message[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * @return Message[]
     */
    public function findConversationMessages(\App\Entity\Chat\Conversation $conversation, int $limit = 50): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.conversation = :conv')
            ->setParameter('conv', $conversation)
            ->orderBy('m.created_at', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

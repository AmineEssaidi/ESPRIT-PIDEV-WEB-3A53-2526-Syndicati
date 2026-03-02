<?php
namespace App\Repository\Chat;

use App\Entity\Chat\ConversationParticipant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConversationParticipant>
 *
 * @method ConversationParticipant|null find($id, $lockMode = null, $lockVersion = null)
 * @method ConversationParticipant|null findOneBy(array $criteria, array $orderBy = null)
 * @method ConversationParticipant[]    findAll()
 * @method ConversationParticipant[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ConversationParticipantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConversationParticipant::class);
    }
}

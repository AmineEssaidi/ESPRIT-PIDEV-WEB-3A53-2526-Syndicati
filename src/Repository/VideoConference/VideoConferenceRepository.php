<?php

namespace App\Repository\VideoConference;

use App\Entity\VideoConference\VideoConference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VideoConference>
 *
 * @method VideoConference|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoConference|null findOneBy(array $criteria, array $orderBy = null)
 * @method VideoConference[]    findAll()
 * @method VideoConference[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VideoConferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoConference::class);
    }

    public function findActiveByRoomToken(string $roomToken): ?VideoConference
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.roomToken = :token')
            ->andWhere('v.expires_at > :now')
            ->andWhere('v.status != :status')
            ->setParameter('token', $roomToken)
            ->setParameter('now', new \DateTime())
            ->setParameter('status', 'ended')
            ->getQuery()
            ->getOneOrNullResult();
    }
}

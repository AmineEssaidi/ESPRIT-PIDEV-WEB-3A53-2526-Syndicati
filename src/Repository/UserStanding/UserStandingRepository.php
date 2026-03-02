<?php
namespace App\Repository\UserStanding;

use App\Entity\UserStanding\UserStanding;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserStanding>
 *
 * @method UserStanding|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserStanding|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserStanding[]    findAll()
 * @method UserStanding[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserStandingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserStanding::class);
    }
}

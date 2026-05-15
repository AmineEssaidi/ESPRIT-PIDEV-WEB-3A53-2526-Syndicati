<?php

namespace App\Repository\Syndicat;

use App\Entity\Syndicat\Reclamation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reclamation>
 *
 * @method Reclamation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Reclamation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Reclamation[]    findAll()
 * @method Reclamation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReclamationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reclamation::class);
    }

    public function findByUserId(int $userId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('r.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByStatut(string $statut): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.statutreclamation = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('r.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getOperationalStats(): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.statutreclamation AS status, COUNT(r.id) AS total')
            ->groupBy('r.statutreclamation')
            ->getQuery()
            ->getArrayResult();

        $stats = [
            'total' => 0,
            'active' => 0,
            'en_attente' => 0,
            'refuse' => 0,
            'termine' => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            $total = (int) ($row['total'] ?? 0);
            if (array_key_exists($status, $stats)) {
                $stats[$status] = $total;
            }
            $stats['total'] += $total;
        }

        return $stats;
    }
}

<?php
namespace App\Service\UserStanding;

use App\Entity\User\User;
use App\Entity\UserStanding\UserStanding;
use App\Repository\UserStanding\UserStandingRepository;
use Doctrine\ORM\EntityManagerInterface;

class UserStandingService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserStandingRepository $userStandingRepository
    ) {
    }

    /**
     * Award points to a user and handle level up logic.
     */
    public function awardPoints(User $user, int $pointsToAdd): void
    {
        $standing = $this->userStandingRepository->findOneBy(['user' => $user]);

        if (!$standing) {
            $standing = new UserStanding();
            $standing->setUser($user);
            $standing->setLevel(1);
            $standing->setPoints(0);
            $standing->setStandingLabel('NORMAL');
            $standing->setUpdatedAt(new \DateTime());
            $this->em->persist($standing);
        }

        $newPoints = $standing->getPoints() + $pointsToAdd;
        $standing->setPoints($newPoints);

        // Level up logic (e.g. every 100 points = 1 level)
        $newLevel = (int) floor($newPoints / 100) + 1;
        if ($newLevel > $standing->getLevel()) {
            $standing->setLevel($newLevel);
        }

        $standing->setUpdatedAt(new \DateTime());
        $this->em->flush();
    }

    /**
     * Award points based on a predefined action type.
     */
    public function awardForAction(User $user, string $action): void
    {
        $points = match ($action) {
            'CREATE_EVENT' => 20,
            'FORUM_POST' => 15,
            'FORUM_COMMENT' => 5,
            'FRIEND_REQUEST' => 5,
            'HEARTBEAT' => 10,
            default => 1,
        };

        $this->awardPoints($user, $points);
    }

    /**
     * Track user activity and award points every 15 minutes.
     * Should be called on major page loads or via AJAX heartbeat.
     */
    public function updateHeartbeat(User $user, \Symfony\Component\HttpFoundation\Request $request): void
    {
        $session = $request->getSession();
        $lastHeartbeat = $session->get('user_standing_last_heartbeat');
        $now = time();

        // 15 minutes = 900 seconds
        if (!$lastHeartbeat || ($now - $lastHeartbeat) >= 900) {
            $this->awardForAction($user, 'HEARTBEAT');
            $session->set('user_standing_last_heartbeat', $now);
        }
    }
}

<?php

namespace App\Service\User;

use App\Entity\User\Notification;
use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly \App\Service\Log\LogBuffer $logBuffer
    ) {
    }

    /**
     * Creates and persists a new notification for a user.
     */
    public function notify(User $user, string $type, string $sourceType, int $sourceId, ?string $title = null, ?string $content = null): Notification
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setSourceType($sourceType);
        $notification->setSourceId($sourceId);
        $notification->setTitle($title);
        $notification->setContent($content);
        $notification->setIsRead(false);
        $notification->setCreatedAt(new \DateTime());

        $this->em->persist($notification);
        $this->logBuffer->push($notification);

        return $notification;
    }
}

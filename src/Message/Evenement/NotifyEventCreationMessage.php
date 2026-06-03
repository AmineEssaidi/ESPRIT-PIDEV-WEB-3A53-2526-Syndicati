<?php

namespace App\Message\Evenement;

final class NotifyEventCreationMessage
{
    public function __construct(
        private readonly int $eventId
    ) {
    }

    public function getEventId(): int
    {
        return $this->eventId;
    }
}

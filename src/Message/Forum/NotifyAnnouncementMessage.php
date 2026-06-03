<?php

namespace App\Message\Forum;

final class NotifyAnnouncementMessage
{
    public function __construct(
        private readonly int $publicationId
    ) {
    }

    public function getPublicationId(): int
    {
        return $this->publicationId;
    }
}

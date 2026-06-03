<?php

namespace App\Message\Evenement;

final class NotifyParticipationConfirmationMessage
{
    public function __construct(
        private readonly int $participationId
    ) {
    }

    public function getParticipationId(): int
    {
        return $this->participationId;
    }
}

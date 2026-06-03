<?php

namespace App\Message\Forum;

final class NotifyCommentMessage
{
    public function __construct(
        private readonly int $commentId
    ) {
    }

    public function getCommentId(): int
    {
        return $this->commentId;
    }
}

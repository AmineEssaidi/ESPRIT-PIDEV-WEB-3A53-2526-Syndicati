<?php

namespace App\Service;

use App\Entity\Forum\Publication;

class PublicationManager
{
    public function validate(Publication $publication): bool
    {
        if (strlen((string) $publication->getTitrePub()) < 5) {
            throw new \InvalidArgumentException('Title must be at least 5 characters long');
        }

        if (!in_array($publication->getCategoriePub(), Publication::CATEGORIES)) {
            throw new \InvalidArgumentException('Invalid category');
        }

        return true;
    }
}

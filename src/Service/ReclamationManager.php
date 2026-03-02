<?php

namespace App\Service;

use App\Entity\Syndicat\Reclamation;

class ReclamationManager
{
    public function validate(Reclamation $reclamation): bool
    {
        if (strlen((string) $reclamation->getTitrereclamations()) < 5) {
            throw new \InvalidArgumentException('Title must be at least 5 characters long');
        }

        if (strlen((string) $reclamation->getDescreclamation()) < 10) {
            throw new \InvalidArgumentException('Description must be at least 10 characters long');
        }

        return true;
    }
}

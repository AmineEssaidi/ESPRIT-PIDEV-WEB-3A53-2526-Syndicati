<?php

namespace App\Service;

use App\Entity\Evenement\Evenement;

class EvenementManager
{
    public function validate(Evenement $event): bool
    {
        if ($event->getNbPlaces() < 0) {
            throw new \InvalidArgumentException('Number of places cannot be negative');
        }

        if (strlen((string) $event->getDescriptionEvent()) < 10) {
            throw new \InvalidArgumentException('Description must be at least 10 characters long');
        }

        return true;
    }
}

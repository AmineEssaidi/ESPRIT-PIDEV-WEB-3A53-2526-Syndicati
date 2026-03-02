<?php

namespace App\Service;

use App\Entity\Residence\Residence;

class ResidenceManager
{
    public function validate(Residence $residence): bool
    {
        if (strlen((string) $residence->getNomR()) > 40) {
            throw new \InvalidArgumentException('Residence name too long (max 40)');
        }

        if (strlen((string) $residence->getAdresse()) > 100) {
            throw new \InvalidArgumentException('Address too long (max 100)');
        }

        return true;
    }
}

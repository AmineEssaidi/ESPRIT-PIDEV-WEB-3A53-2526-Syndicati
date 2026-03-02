<?php

namespace App\Service;

use App\Entity\User\User;

class UserManager
{
    public function validate(User $user): bool
    {
        if (empty($user->getFirstName()) || empty($user->getLastName())) {
            throw new \InvalidArgumentException('First and last names are mandatory');
        }

        if (!preg_match('/^[a-zA-ZÀ-ÿ\s\-]+$/', $user->getFirstName()) || !preg_match('/^[a-zA-ZÀ-ÿ\s\-]+$/', $user->getLastName())) {
            throw new \InvalidArgumentException('Names cannot contain numbers or special characters');
        }

        if (!filter_var($user->getEmailUser(), FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }

        return true;
    }
}

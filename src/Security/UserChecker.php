<?php

namespace App\Security;

use App\Entity\User\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;

/**
 * Blocks login for users who are not yet verified by an admin (is_verified = 0).
 */
class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->getIsVerified()) {
            throw new CustomUserMessageAccountStatusException('Your account is not yet verified by an administrator. You cannot log in until an admin verifies your account.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // No post-auth checks needed for verification
    }
}

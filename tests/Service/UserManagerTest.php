<?php

namespace App\Tests\Service;

use App\Entity\User\User;
use App\Service\UserManager;
use PHPUnit\Framework\TestCase;

class UserManagerTest extends TestCase
{
    public function testValidUser()
    {
        $user = new User();
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $user->setEmailUser('john.doe@example.com');

        $manager = new UserManager();
        $this->assertTrue($manager->validate($user));
    }

    public function testMissingName()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('First and last names are mandatory');

        $user = new User();
        $user->setFirstName('');
        $user->setLastName('Doe');
        $user->setEmailUser('john.doe@example.com');

        $manager = new UserManager();
        $manager->validate($user);
    }

    public function testInvalidNameCharacters()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Names cannot contain numbers or special characters');

        $user = new User();
        $user->setFirstName('John123');
        $user->setLastName('Doe');
        $user->setEmailUser('john.doe@example.com');

        $manager = new UserManager();
        $manager->validate($user);
    }

    public function testInvalidEmail()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email format');

        $user = new User();
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $user->setEmailUser('john.doe-at-example.com');

        $manager = new UserManager();
        $manager->validate($user);
    }
}

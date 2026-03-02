<?php

namespace App\Tests\Service;

use App\Entity\Evenement\Evenement;
use App\Service\EvenementManager;
use PHPUnit\Framework\TestCase;

class EvenementManagerTest extends TestCase
{
    public function testValidEvenement()
    {
        $event = new Evenement();
        $event->setNbPlaces(50);
        $event->setDescriptionEvent('This is a valid event description for the annual meeting.');

        $manager = new EvenementManager();
        $this->assertTrue($manager->validate($event));
    }

    public function testNegativePlaces()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Number of places cannot be negative');

        $event = new Evenement();
        $event->setNbPlaces(-5);
        $event->setDescriptionEvent('Valid description');

        $manager = new EvenementManager();
        $manager->validate($event);
    }

    public function testShortDescription()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Description must be at least 10 characters long');

        $event = new Evenement();
        $event->setNbPlaces(10);
        $event->setDescriptionEvent('Too short');

        $manager = new EvenementManager();
        $manager->validate($event);
    }
}

<?php

namespace App\Tests\Service;

use App\Entity\Syndicat\Reclamation;
use App\Service\ReclamationManager;
use PHPUnit\Framework\TestCase;

class ReclamationManagerTest extends TestCase
{
    public function testValidReclamation()
    {
        $reclamation = new Reclamation();
        $reclamation->setTitrereclamations('Broken elevator');
        $reclamation->setDescreclamation('The elevator in block A is not working since this morning.');

        $manager = new ReclamationManager();
        $this->assertTrue($manager->validate($reclamation));
    }

    public function testShortTitle()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Title must be at least 5 characters long');

        $reclamation = new Reclamation();
        $reclamation->setTitrereclamations('Help');
        $reclamation->setDescreclamation('The elevator in block A is not working.');

        $manager = new ReclamationManager();
        $manager->validate($reclamation);
    }

    public function testShortDescription()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Description must be at least 10 characters long');

        $reclamation = new Reclamation();
        $reclamation->setTitrereclamations('Problem');
        $reclamation->setDescreclamation('Fix it');

        $manager = new ReclamationManager();
        $manager->validate($reclamation);
    }
}

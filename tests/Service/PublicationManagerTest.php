<?php

namespace App\Tests\Service;

use App\Entity\Forum\Publication;
use App\Service\PublicationManager;
use PHPUnit\Framework\TestCase;

class PublicationManagerTest extends TestCase
{
    public function testValidPublication()
    {
        $publication = new Publication();
        $publication->setTitrePub('New Feature Update');
        $publication->setCategoriePub('Announcement');

        $manager = new PublicationManager();
        $this->assertTrue($manager->validate($publication));
    }

    public function testShortTitle()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Title must be at least 5 characters long');

        $publication = new Publication();
        $publication->setTitrePub('New');
        $publication->setCategoriePub('Announcement');

        $manager = new PublicationManager();
        $manager->validate($publication);
    }

    public function testInvalidCategory()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid category');

        $publication = new Publication();
        $publication->setTitrePub('New Feature');
        $publication->setCategoriePub('InvalidCategory');

        $manager = new PublicationManager();
        $manager->validate($publication);
    }
}

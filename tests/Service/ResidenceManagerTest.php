<?php

namespace App\Tests\Service;

use App\Entity\Residence\Residence;
use App\Service\ResidenceManager;
use PHPUnit\Framework\TestCase;

class ResidenceManagerTest extends TestCase
{
    public function testValidResidence()
    {
        $residence = new Residence();
        $residence->setNomR('Horizon Residence');
        $residence->setAdresse('123 Modern complex, Suite 404');

        $manager = new ResidenceManager();
        $this->assertTrue($manager->validate($residence));
    }

    public function testLongName()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Residence name too long (max 40)');

        $residence = new Residence();
        $residence->setNomR(str_repeat('A', 41));
        $residence->setAdresse('Valid address');

        $manager = new ResidenceManager();
        $manager->validate($residence);
    }

    public function testLongAddress()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Address too long (max 100)');

        $residence = new Residence();
        $residence->setNomR('Valid Name');
        $residence->setAdresse(str_repeat('A', 101));

        $manager = new ResidenceManager();
        $manager->validate($residence);
    }
}

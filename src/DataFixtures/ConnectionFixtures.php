<?php

namespace App\DataFixtures;

use App\Entity\Connection;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ConnectionFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $testConnection = new Connection();
        $testConnection->setCrmUrl('lomax48.retailcrm.ru');
        $testConnection->setApiKey('5fdZEgnP0AHQzYtG5CPnKykCRujQBVNK');
        $testConnection->setDeliveryLogin('hYdz3J');
        $testConnection->setDeliveryPassword('6jUzhQ7iwfgj0');
        $testConnection->setDeliveryIKN('9990000112');

        $manager->persist($testConnection);

        $manager->flush();
    }
}

<?php

namespace App\Services;

use App\Component\Exception\AlreadyExistsException;
use App\Component\Exception\NotFoundException;
use App\Entity\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class ConnectionService
 */
class ConnectionService
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function createConnection(Connection $connection): Connection
    {
        $connectionRepository = $this->entityManager->getRepository(Connection::class);
        $duplicate = $connectionRepository->findOneBy([
            'crmUrl' => $connection->getCrmUrl() ?? '',
            'apiKey' => $connection->getApiKey() ?? '',
        ]);

        if ($duplicate !== null) {
            throw new AlreadyExistsException('This connection already exists');
        }

        $conn = new Connection();
        $conn->setClientId($connection->getDeliveryLogin() . $connection->getDeliveryIKN());
        $conn->setDeliveryLogin($connection->getDeliveryLogin());
        $conn->setDeliveryPassword($connection->getDeliveryPassword());
        $conn->setCrmUrl($connection->getCrmUrl() ?? '');
        $conn->setApiKey($connection->getApiKey() ?? '');
        $conn->setIsActive(true);

        $this->entityManager->persist($conn);

        return $conn;
    }

    public function updateConnection(Connection $connection, Connection $user): Connection
    {
        $user->setDeliveryLogin($connection->getDeliveryLogin());
        $user->setDeliveryPassword($connection->getDeliveryPassword());

        if ($connection->getCrmUrl() !== null) {
            $user->setCrmUrl($connection->getCrmUrl());
        }
        if ($connection->getApiKey() !== null) {
            $user->setApiKey($connection->getApiKey());
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}

<?php

namespace App\Services;

use App\Entity\Connection;
use App\Utils\ConfigurationBuilder;
use Psr\Log\LoggerInterface;
use RetailCrm\Api\Factory\SimpleClientFactory;
use RetailCrm\Api\Interfaces\ApiExceptionInterface;
use RetailCrm\Api\Interfaces\ClientExceptionInterface;
use RetailCrm\Api\Model\Request\Integration\IntegrationModulesEditRequest;

class IntegrationService
{
    protected $configurationBuilder;
    protected $logger;

    public function __construct(ConfigurationBuilder $configurationBuilder, LoggerInterface $logger)
    {
        $this->configurationBuilder = $configurationBuilder;
        $this->logger = $logger;
    }

    public function createOrUpdate(Connection $connection): void
    {
        $module = $this->configurationBuilder->build($connection);

        if ($connection->getCrmUrl() !== null && $connection->getApiKey() !== null) {
            $client = SimpleClientFactory::createClient($connection->getCrmUrl(), $connection->getApiKey());

            try {
                $client->integration->edit(
                    ConfigurationBuilder::INTEGRATION_CODE,
                    new IntegrationModulesEditRequest($module)
                );
            } catch (ApiExceptionInterface $exception) {
                $this->logger->error(
                    sprintf(
                        'Error from system API (status code: %d): %s',
                        $exception->getCode(),
                        $exception->getMessage()
                    ),
                    $exception->getErrorResponse()->errors ?? []
                );

                throw $exception;
            }
        }
    }
}
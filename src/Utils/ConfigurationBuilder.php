<?php

namespace App\Utils;

use App\Entity\Connection;
use App\Services\CallbackService;
use RetailCrm\Api\Model\Entity\Integration\Delivery\DeliveryConfiguration;
use RetailCrm\Api\Model\Entity\Integration\Delivery\DeliveryDataField;
use RetailCrm\Api\Model\Entity\Integration\Delivery\Status;
use RetailCrm\Api\Model\Entity\Integration\IntegrationModule;
use RetailCrm\Api\Model\Entity\Integration\Integrations;
use Symfony\Component\Routing\RouterInterface;

class ConfigurationBuilder
{
    private RouterInterface $router;
    /**
     * @var array[]
     */
    protected array $mapperStatuses;

    public const INTEGRATION_CODE = 'pickpoint';
    public const ID_REQUIRED_FIELDS = 'phone';
    public const ID_PAYER_TYPE = 'sender';
    public const ID_AVAILABLE_COUNTRIES = ['RU', 'BY'];

    /**
     * @param RouterInterface $router
     * @param array[] $mapperStatuses
     */
    public function __construct(RouterInterface $router, array $mapperStatuses)
    {
        $this->router = $router;
        $this->mapperStatuses = $mapperStatuses;
    }

    public static function generateModuleCode(Connection $connection): string
    {
        return sprintf('%s-%d', static::INTEGRATION_CODE, $connection->getId());
    }

    public function build(Connection $connection): IntegrationModule
    {
        $baseUrl = sprintf('%s://%s:%d', 'http', '109.195.6.211', 80);

        $module = new IntegrationModule();
        $module->code = static::generateModuleCode($connection);

        if ($connection->isActive() !== null) {
            $module->active = $connection->isActive();
        }

        if ($connection->getClientId() !== null) {
            $module->clientId = $connection->getClientId();
        }

        $module->integrationCode = self::INTEGRATION_CODE;
        $module->name = 'PickPoint';
        $module->baseUrl = $baseUrl . '/';
        $module->actions = ['activity' => '/api/v1/activity'];
        $module->availableCountries = self::ID_AVAILABLE_COUNTRIES;
        $module->accountUrl = $baseUrl . '/register';

        $integrations = new Integrations();
        $integrations->delivery = $this->buildConfiguration();
        $integrations->delivery->description = 'Интеграция со службой доставки PickPoint';
        $module->integrations = $integrations;

        return $module;
    }

    public function buildConfiguration(): DeliveryConfiguration
    {
        $configuration = new DeliveryConfiguration();
        $configuration->actions = [
            'calculate' => '/api/v1/calculate',
            'save' => '/api/v1/save',
            'get' => '/api/v1/get',
            'delete' => '/api/v1/delete',
            'print' => 'api/v1/makeLabel',
        ];

        $configuration->payerType = [self::ID_PAYER_TYPE];
        $configuration->requiredFields = [self::ID_REQUIRED_FIELDS];
        $configuration->availableCountries = self::ID_AVAILABLE_COUNTRIES;
        $configuration->selfShipmentAvailable = true;
        $configuration->allowPackages = false;
        $configuration->codAvailable = false;
        $configuration->duplicateOrderProductSupported = false;
        $configuration->rateDeliveryCost = true;
        $configuration->statusList = $this->statusList();
        $configuration->deliveryDataFieldList = $this->buildDeliveryDataFieldList();

        return $configuration;
    }

    public function statusList(): array
    {
        $result = [];

        foreach ($this->mapperStatuses as $status) {
            $resultStatus = new Status();
            $resultStatus->code = $status['code'];
            $resultStatus->name = $status['name'];

            $result[] = $resultStatus;
        }

        return $result;
    }

    public function buildDeliveryDataFieldList(): array
    {
        $result = [];

        $dataField = new DeliveryDataField();
        $dataField->code = 'postamat';
        $dataField->label = 'Пункт выдачи заказов';
        $dataField->type = 'choice';
        $dataField->hint = 'Выбор пункта выдачи заказов';
        $dataField->affectsCost = false;
        $dataField->required = true;
        $dataField->editable = true;
        $dataField->choices = [
            [
                'value' => 'hello',
                'label' => 'by',
            ]
        ];


        $result[] = $dataField;

        return $result;
    }
}

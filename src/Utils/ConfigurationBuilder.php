<?php

namespace App\Utils;

use App\Entity\Connection;
use App\Services\CallbackService;
use RetailCrm\Api\Model\Entity\Integration\Delivery\DeliveryConfiguration;
use RetailCrm\Api\Model\Entity\Integration\Delivery\DeliveryDataField;
use RetailCrm\Api\Model\Entity\Integration\Delivery\Plate;
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
        $module->accountUrl = $baseUrl . '/delivery/';

        $integrations = new Integrations();
        $integrations->delivery = $this->buildConfiguration();
        $integrations->delivery->description = '???????????????????? ???? ?????????????? ???????????????? PickPoint';
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
            'print' => 'api/v1/print',
            'shipmentPointList' => 'api/v1/shipmentPointList',
            'shipmentSave' => 'api/v1/shipmentSave',
            'shipmentDelete' => 'api/v1/shipmentDelete',
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
        $configuration->plateList = $this->plateList();

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

        $gettingType = new DeliveryDataField();
        $gettingType->code = 'gettingType';
        $gettingType->label = '?????? ?????????? ??????????????????????';
        $gettingType->hint = '?????????? ???????? ?????????? ??????????????????????';
        $gettingType->type = 'choice';
        $gettingType->required = true;
        $gettingType->editable = true;
        $gettingType->affectsCost = false;
        $gettingType->choices = [
            [
                'value' => 101,
                'label' => '?????????? ??????????????',
            ],
            [
                'value' => 102,
                'label' => '?? ???????? ???????????? ???????????????????? ????????????',
            ],
            [
                'value' => 103,
                'label' => '?? ???????? ???????????? ???? ??????????',
            ],
            [
                'value' => 104,
                'label' => '?? ???????? ???????????? ???? (?????????????????????????????? ???????????? ?? ???????????? ????)',
            ],
        ];

        $result[] = $gettingType;

        $deliveryFat = new DeliveryDataField();
        $deliveryFat->code = 'deliveryFat';
        $deliveryFat->label = '???????????? ?????? ???? ???????????????????? ??????????';
        $deliveryFat->hint = '???????????? ?????? ???? ???????????????????? ??????????';
        $deliveryFat->type = 'choice';
        $deliveryFat->required = true;
        $deliveryFat->editable = true;
        $deliveryFat->affectsCost = false;
        $deliveryFat->choices = [
            [
                'value' => 20,
                'label' => '?????? 20%',
            ],
            [
                'value' => 10,
                'label' => '?????? 10%',
            ],
            [
                'value' => 0,
                'label' => '?????? ??????',
            ],
        ];

        $result[] = $deliveryFat;

        $postageType = new DeliveryDataField();
        $postageType->code = 'postageType';
        $postageType->label = '?????? ??????????????????????';
        $postageType->hint = '?????????? ???????? ??????????????????????';
        $postageType->type = 'choice';
        $postageType->required = true;
        $postageType->editable = true;
        $postageType->affectsCost = false;
        $postageType->choices = [
            [
                'value' => 10001,
                'label' => '???????????????????? ??????????',
            ],
            [
                'value' => 10003,
                'label' => '?????????????????????? ?? ???????????????????? ????????????????',
            ],
        ];

        $result[] = $postageType;

        $deliveryMode = new DeliveryDataField();
        $deliveryMode->code = 'deliveryMode';
        $deliveryMode->label = '?????????? ????????????????';
        $deliveryMode->hint = '?????????? ???????????? ????????????????';
        $deliveryMode->type = 'choice';
        $deliveryMode->required = true;
        $deliveryMode->editable = true;
        $deliveryFat->affectsCost = false;
        $deliveryMode->choices = [
            [
                'value' => 1,
                'label' => '????????????????',
            ],
            [
                'value' => 2,
                'label' => '????????????????????????',
            ],
        ];

        $result[] = $deliveryMode;

        $barCode = new DeliveryDataField();
        $barCode->code = 'barCode';
        $barCode->label = '??????????-??????';
        $barCode->type = 'text';
        $barCode->visible = false;
        $barCode->required = false;
        $barCode->affectsCost = false;
        $barCode->editable = true;

        $result[] = $barCode;

        return $result;
    }

    public function plateList(): array
    {
        $plateList = [];

        $plate = new Plate();

        $plate->code = 'pickpoint';
        $plate->type = 'order';
        $plate->label = '???????????????? ?????????? PickPoint';

        $plateList[] = $plate;

        return $plateList;
    }
}

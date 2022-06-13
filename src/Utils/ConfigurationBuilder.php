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
        $gettingType->label = 'Тип сдачи отправления';
        $gettingType->hint = 'Выбор типа сдачи отправления';
        $gettingType->type = 'choice';
        $gettingType->required = true;
        $gettingType->editable = true;
        $gettingType->affectsCost = false;
        $gettingType->choices = [
            [
                'value' => 101,
                'label' => 'Вызов курьера',
            ],
            [
                'value' => 102,
                'label' => 'В окне приёма сервисного центра',
            ],
            [
                'value' => 103,
                'label' => 'В окне приёма ПТ валом',
            ],
            [
                'value' => 104,
                'label' => 'В окне приёма ПТ (самостоятельный развоз в нужный ПТ)',
            ],
        ];

        $result[] = $gettingType;

        $deliveryFat = new DeliveryDataField();
        $deliveryFat->code = 'deliveryFat';
        $deliveryFat->label = 'Ставка НДС по сервисному сбору';
        $deliveryFat->hint = 'Ставка НДС по сервисному сбору';
        $deliveryFat->type = 'choice';
        $deliveryFat->required = true;
        $deliveryFat->editable = true;
        $deliveryFat->affectsCost = false;
        $deliveryFat->choices = [
            [
                'value' => 20,
                'label' => 'НДС 20%',
            ],
            [
                'value' => 10,
                'label' => 'НДС 10%',
            ],
            [
                'value' => 0,
                'label' => 'Без НДС',
            ],
        ];

        $result[] = $deliveryFat;

        $postageType = new DeliveryDataField();
        $postageType->code = 'postageType';
        $postageType->label = 'Вид отправления';
        $postageType->hint = 'Выбор вида отправления';
        $postageType->type = 'choice';
        $postageType->required = true;
        $postageType->editable = true;
        $postageType->affectsCost = false;
        $postageType->choices = [
            [
                'value' => 10001,
                'label' => 'Оплаченный заказ',
            ],
            [
                'value' => 10003,
                'label' => 'Отправление с наложенным платежом',
            ],
        ];

        $result[] = $postageType;

        $deliveryMode = new DeliveryDataField();
        $deliveryMode->code = 'deliveryMode';
        $deliveryMode->label = 'Режим доставки';
        $deliveryMode->hint = 'Выбор режима доставки';
        $deliveryMode->type = 'choice';
        $deliveryMode->required = true;
        $deliveryMode->editable = true;
        $deliveryFat->affectsCost = false;
        $deliveryMode->choices = [
            [
                'value' => 1,
                'label' => 'Стандарт',
            ],
            [
                'value' => 2,
                'label' => 'Приоритетный',
            ],
        ];

        $result[] = $deliveryMode;

        $barCode = new DeliveryDataField();
        $barCode->code = 'barCode';
        $barCode->label = 'Штрих-код';
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
        $plate->label = 'Печатная форма PickPoint';

        $plateList[] = $plate;

        return $plateList;
    }
}

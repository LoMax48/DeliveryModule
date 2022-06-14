<?php

namespace App\Services;

use App\Entity\Connection;
use App\Entity\Delivery;
use App\Utils\Transformers\CityTransformer;
use App\Utils\Transformers\RegionTransformer;
use Doctrine\ORM\EntityManagerInterface;
use RetailCrm\Api\Model\Callback\Entity\Delivery\RequestProperty\RequestCalculate;
use RetailCrm\Api\Model\Callback\Entity\Delivery\RequestProperty\RequestDelete;
use RetailCrm\Api\Model\Callback\Entity\Delivery\RequestProperty\RequestPrint;
use RetailCrm\Api\Model\Callback\Entity\Delivery\RequestProperty\RequestSave;
use RetailCrm\Api\Model\Callback\Entity\Delivery\ResponseProperty\ResponseCalculate;
use RetailCrm\Api\Model\Callback\Entity\Delivery\ResponseProperty\ResponseSave;
use RetailCrm\Api\Model\Callback\Entity\Delivery\Terminal;
use RetailCrm\Api\Model\Callback\Response\Delivery\CalculateResponse;
use RetailCrm\Api\Model\Callback\Response\Delivery\SaveResponse;
use RetailCrm\Api\Model\Callback\Response\ErrorResponse;
use RetailCrm\Api\Model\Response\SuccessResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CallbackService
{
    private EntityManagerInterface $entityManager;
    private HttpClientInterface $httpClient;
    private PickPointService $pickPointService;

    public function __construct(
        EntityManagerInterface $entityManager,
        HttpClientInterface $httpClient,
        PickPointService $pickPointService
    ) {
        $this->entityManager = $entityManager;
        $this->httpClient = $httpClient;
        $this->pickPointService = $pickPointService;
    }

    public function save(Connection $connection, RequestSave $requestSave)
    {
        $deliveryResponse = $this->httpClient->request(
            'POST',
            'https://e-solution.pickpoint.ru/apitest/v2/CreateShipment',
            [
                'json' => [
                    'SessionId' => $this->pickPointService->startSession($connection),
                    'Sendings' => [
                        [
                            'EDTN' => $connection->getClientId() . time(),
                            'IKN' => $connection->getDeliveryIKN(),
                            'Invoice' => [
                                'SenderCode' => $requestSave->orderNumber,
                                'Description' => $requestSave->order,
                                'RecipientName' => $requestSave->customer['lastName'] .
                                    $requestSave->customer['firstName'],
                                'PostamatNumber' => $requestSave->delivery['deliveryAddress']['terminal'],
                                'MobilePhone' => $requestSave->customer['phones']['0'],
                                'PostageType' => $requestSave->delivery['extraData']['postageType'],
                                'GettingType' => $requestSave->delivery['extraData']['gettingType'],
                                'PayType' => 1,
                                'Sum' => 0,
                                'DeliveryFat' => $requestSave->delivery['extraData']['deliveryFat'],
                                'DeliveryMode' => $requestSave->delivery['extraData']['deliveryMode'],
                                'ClientDeliveryDate' => [
                                    'From' => $requestSave->delivery['deliveryDate'],
                                    'To' => $requestSave->delivery['deliveryDate'],
                                ],
                                'SenderCity' => [
                                    'CityName' => CityTransformer::toPickPointFormat(
                                        $requestSave->delivery['deliveryAddress']['city']
                                    ),
                                    'RegionName' => RegionTransformer::toPickPointFormat(
                                        $requestSave->delivery['shipmentAddress']['region']
                                    ),
                                ],
                                'Places' => [
                                    [
                                        'BarCode' => '',
                                        'Width' => round($requestSave->packages[0]['width'] / 10),
                                        'Height' => round($requestSave->packages[0]['height'] / 10),
                                        'Depth' => round($requestSave->packages[0]['length'] / 10),
                                        'Weight' => round($requestSave->packages[0]['weight'] / 1000, 2),
                                        'SubEncloses' => [
                                            [
                                                'ProductCode' => $requestSave->packages[0]['items'][0]['offerId'],
                                                'GoodsName' => $requestSave->packages[0]['items'][0]['name'],
                                                'Price' => $requestSave->packages[0]['items'][0]['cost'],
                                                'Vat' => 10,
                                                'Description' => $requestSave->packages[0]['items'][0]['name'],
                                                'Upi' => 1,
                                            ],
                                        ]
                                    ],
                                ]
                            ]
                        ],
                    ]
                ]
            ]
        );

        $deliveryData = $deliveryResponse->toArray();

        if (count($deliveryData['CreatedSendings']) > 0) {
            $responseSave = new ResponseSave();
            $responseSave->deliveryId = $deliveryData['CreatedSendings'][0]['InvoiceNumber'];
            $responseSave->trackNumber = $deliveryData['CreatedSendings'][0]['InvoiceNumber'];
            $responseSave->extraData['barCode'] = $deliveryData['CreatedSendings'][0]['Barcode'];

            $saveResponse = new SaveResponse();
            $saveResponse->result = $responseSave;

            $delivery = new Delivery();
            $delivery->setConnection($connection);
            $delivery->setDeliveryId($responseSave->deliveryId);
            $delivery->setOrderId($requestSave->orderNumber);
            $delivery->setSum($requestSave->delivery['cost']);
            $delivery->setDate($requestSave->delivery['deliveryDate']);

            $this->entityManager->persist($delivery);
            $this->entityManager->flush();

            return $saveResponse;
        }

        $errorResponse = new ErrorResponse();
        $errorResponse->errorMsg = 'Ошибка оформления доставки. Возможно, данное отправление уже создано.';

        return $errorResponse;
    }

    public function update(Connection $connection, RequestSave $requestSave)
    {
        $deliveryResponse = $this->httpClient->request(
            'POST',
            'https://e-solution.pickpoint.ru/apitest/updateInvoice',
            [
                'json' => [
                    'SessionId' => $this->pickPointService->startSession($connection),
                    'InvoiceNumber' => $requestSave->deliveryId,
                    'GCInvoiceNumber' => $requestSave->deliveryId,
                    'PostamatNumber' => $requestSave->delivery['deliveryAddress']['terminal'],
                    'Phone' => $requestSave->customer['phones'][0],
                    'RecipientName' => $requestSave->customer['lastName'] . ' ' . $requestSave->customer['firstName'],
                    'BarCode' => $requestSave->delivery['extraData']['barCode'],
                    'SubEncloses' => [
                        [
                            'ProductCode' => $requestSave->packages[0]['items'][0]['offerId'],
                            'GoodsName' => $requestSave->packages[0]['items'][0]['name'],
                            'Name' => $requestSave->packages[0]['items'][0]['name'],
                            'Price' => $requestSave->packages[0]['items'][0]['cost'],
                            'Vat' => 10,
                            'Description' => $requestSave->packages[0]['items'][0]['name'],
                            'Upi' => 1,
                        ],
                    ]
                ]
            ]
        );

        $deliveryData = $deliveryResponse->toArray();

        $responseSave = new ResponseSave();
        $responseSave->deliveryId = $deliveryData['InvoiceNumber'];

        $saveResponse = new SaveResponse();
        $saveResponse->result = $responseSave;
        $saveResponse->success = true;

        return $saveResponse;
    }

    public function delete(Connection $connection, RequestDelete $requestDelete)
    {
        $deliveryResponse = $this->httpClient->request(
            'POST',
            'https://e-solution.pickpoint.ru/apitest/cancelInvoice',
            [
                'json' => [
                    'SessionId' => $this->pickPointService->startSession($connection),
                    'IKN' => $connection->getDeliveryIKN(),
                    'InvoiceNumber' => $requestDelete->deliveryId,
                ]
            ]
        );

        $deliveryData = $deliveryResponse->toArray();

        if ($deliveryData['Result'] === true) {
            $successResponse = new SuccessResponse();
            $successResponse->success = true;

            return $successResponse;
        }

        $errorResponse = new ErrorResponse();
        $errorResponse->errorMsg = 'Не удалось отменить доставку. Свяжитесь с менеджером.';

        return $errorResponse;
    }

    public function makeLabel(Connection $connection, RequestPrint $requestPrint)
    {
        $deliveryResponse = $this->httpClient->request(
            'POST',
            'https://e-solution.pickpoint.ru/apitest/makelabel',
            [
                'json' => [
                    'SessionId' => $this->pickPointService->startSession($connection),
                    'Invoices' => $requestPrint->deliveryIds[0],
                ]
            ]
        );

        return $deliveryResponse->getContent();
    }

    public function calculate(Connection $connection, RequestCalculate $requestCalculate): CalculateResponse
    {
        $deliveryCity = CityTransformer::toPickPointFormat($requestCalculate->deliveryAddress['city']);
        $deliveryRegion = RegionTransformer::toPickPointFormat($requestCalculate->deliveryAddress['region']);

        $pickPointResponse =
            $this->pickPointService->getShipmentPointsList($connection, $deliveryCity, $deliveryRegion);

        $shipmentPoints = json_decode(
            $pickPointResponse->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $terminals = [];

        foreach ($shipmentPoints as $point) {
            $terminal = new Terminal();
            $terminal->code = $point['Id'];
            $terminal->name = $point['Name'];
            $terminal->address = $point['Address'];
            $terminal->schedule = $point['WorkTime'];
            $terminals[] = $terminal;
        }

        $tariffs = $this->pickPointService->calcTariff(
            $connection,
            $requestCalculate
        );

        $responseCalculate = new ResponseCalculate();
        $responseCalculate->code = 'pickpoint_tariff';
        $responseCalculate->name = 'pickpoint';
        $responseCalculate->type = 'selfDelivery';
        $responseCalculate->pickuppointList = $terminals;

        foreach ($tariffs['Services'] as $service) {
            if ($service['Name'] === 'Тариф Оптимальный Услуга по выдаче отправлений') {
                $responseCalculate->cost = $service['Tariff'];
            }
        }

        $calculateResponse = new CalculateResponse();
        $calculateResponse->result[] = $responseCalculate;
        $calculateResponse->success = true;

        return $calculateResponse;
    }

    public function shipmentPointList(Connection $connection, Request $request)
    {
        $shipmentCity = CityTransformer::toPickPointFormat($request['city']);
        $shipmentRegion = RegionTransformer::toPickPointFormat($request['region']);

        $pickPointResponse =
            $this->pickPointService->getShipmentPointsList($connection, $shipmentCity, $shipmentRegion);

        $shipmentPoints = json_decode(
            $pickPointResponse->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (count($shipmentPoints) > 0) {
            $terminals = [];

            foreach ($shipmentPoints as $point) {
                $terminal = new Terminal();
                $terminal->code = $point['Id'];
                $terminal->name = $point['Name'];
                $terminal->address = $point['Address'];
                $terminal->schedule = $point['WorkTime'];
                $terminals[] = $terminal;
            }

            return [
                'success' => true,
                'result' => $terminals,
            ];
        }

        return ['success' => false];
    }
}

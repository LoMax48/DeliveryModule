<?php

namespace App\Services;

use App\Entity\Connection;
use App\Repository\ConnectionRepository;
use App\Utils\Transformers\CityTransformer;
use App\Utils\Transformers\RegionTransformer;
use Doctrine\Common\Collections\ArrayCollection;
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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CallbackService
{
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient, ConnectionRepository $connectionRepository)
    {
        $this->httpClient = $httpClient;
    }

    public function startSession(Connection $connection): string
    {
        try {
            $response = $this->httpClient->request(
                'POST',
                'https://e-solution.pickpoint.ru/apitest/login',
                [
                    'json' => [
                        'Login' => $connection->getDeliveryLogin(),
                        'Password' => $connection->getDeliveryPassword(),
                    ]
                ]
            );

            $content = $response->toArray();

            return $content['SessionId'] ?: $content['ErrorMessage'];
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
    }

    public function getShipmentPointsList(Connection $connection, string $targetCity): Response
    {
        $response = new JsonResponse();

        try {
            $deliveryResponse = $this->httpClient->request(
                'POST',
                'https://e-solution.pickpoint.ru/apitest/clientpostamatlist',
                [
                    'json' => [
                        'SessionId' => $this->startSession($connection),
                        'IKN' => $connection->getDeliveryIKN()
                    ]
                ]
            );

            $shipmentPoints = new ArrayCollection($deliveryResponse->toArray());

            if ($targetCity) {
                $filteredShipmentPoints = $shipmentPoints->filter(function ($shipmentPoint) use ($targetCity) {
                    return $shipmentPoint['CitiName'] === $targetCity;
                });

                $response->setContent(json_encode($filteredShipmentPoints->getValues(), JSON_THROW_ON_ERROR));
            } else {
                $response->setContent(json_encode($shipmentPoints->getValues(), JSON_THROW_ON_ERROR));
            }
        } catch (\Exception $exception) {
            $response->setContent(json_encode([
                'errorMessage' => $exception->getMessage()
            ], JSON_THROW_ON_ERROR));
        }

        $response->setStatusCode(Response::HTTP_OK);

        return $response;
    }

    public function save(Connection $connection, RequestSave $requestSave)
    {
        $deliveryResponse = $this->httpClient->request(
            'POST',
            'https://e-solution.pickpoint.ru/apitest/v2/CreateShipment',
            [
                'json' => [
                    'SessionId' => $this->startSession($connection),
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
                                'PostageType' => 10001,
                                'GettingType' => 102,
                                'PayType' => 1,
                                'Sum' => 0,
                                'DeliveryMode' => 1,
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
        }

        $saveResponse = new SaveResponse();
        $saveResponse->result = $responseSave;

        return $saveResponse;
    }

    public function delete(Connection $connection, RequestDelete $requestDelete)
    {
        $deliveryResponse = $this->httpClient->request(
            'POST',
            'https://e-solution.pickpoint.ru/apitest/removeinvoicefromreestr',
            [
                'json' => [
                    'SessionId' => $this->startSession($connection),
                    'IKN' => $connection->getDeliveryIKN(),
                    'InvoiceNumber' => $requestDelete->deliveryId,
                ]
            ]
        );

        $successResponse = new SuccessResponse();
        $successResponse->success = true;

        return $successResponse;
    }

    public function calculate(Connection $connection, RequestCalculate $requestCalculate): CalculateResponse
    {
        $deliveryCity = $requestCalculate->deliveryAddress['city'];

        $pickPointResponse = $this->getShipmentPointsList($connection, $deliveryCity);

        $shipmentPoints = json_decode($pickPointResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $terminals = [];

        foreach ($shipmentPoints as $point) {
            $terminal = new Terminal();
            $terminal->code = $point['Id'];
            $terminal->name = $point['Name'];
            $terminal->address = $point['Address'];
            $terminal->schedule = $point['WorkTime'];
            $terminals[] = $terminal;
        }

        $tariffs = $this->calcTariff(
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



    public function calcTariff(Connection $connection, RequestCalculate $requestCalculate)
    {
        if ($connection->getClientId() !== null) {
            try {
                $requestCalculate->deliveryAddress['region'] =
                    RegionTransformer::toPickPointFormat($requestCalculate->deliveryAddress['region']);
                $requestCalculate->shipmentAddress['region'] =
                    RegionTransformer::toPickPointFormat($requestCalculate->shipmentAddress['region']);
                $requestCalculate->deliveryAddress['city'] =
                    CityTransformer::toPickPointFormat($requestCalculate->deliveryAddress['city']);
                $requestCalculate->shipmentAddress['city'] =
                    CityTransformer::toPickPointFormat($requestCalculate->shipmentAddress['city']);

                $data = $this->httpClient->request(
                    'POST',
                    'https://e-solution.pickpoint.ru/apitest/calctariff',
                    [
                        'json' => [
                            'SessionId' => $this->startSession($connection),
                            'IKN' => $connection->getDeliveryIKN(),
                            'FromCity' => $requestCalculate->shipmentAddress['city'],
                            'FromRegion' => $requestCalculate->shipmentAddress['region'],
                            'ToCity' => $requestCalculate->deliveryAddress['city'],
                            'ToRegion' => $requestCalculate->deliveryAddress['region'],
                            'Length' => round($requestCalculate->packages[0]['length'] / 10),
                            'Width' => round($requestCalculate->packages[0]['width'] / 10),
                            'Depth' => round($requestCalculate->packages[0]['height'] / 10),
                            'Weight' => round($requestCalculate->packages[0]['weight'] / 1000, 2),
                        ]
                    ]
                );

                return json_decode($data->getContent(), true, 512, JSON_THROW_ON_ERROR);
            } catch (\Exception $exception) {
                $errorResponse = new ErrorResponse();
                $errorResponse->errorMsg = $exception->getMessage();

                return $errorResponse;
            }
        } else {
            $errorResponse = new ErrorResponse();
            $errorResponse->errorMsg = 'Параметр ClientId не указан.';

            return $errorResponse;
        }
    }

    public function getNearestShipmentPoint(Request $request): Response
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $shipmentPointsResponse = $this->getShipmentPointsList($request);
        $shipmentPoints = new ArrayCollection(json_decode($shipmentPointsResponse->getContent(), true));

        $oneDegreeLength = 80000;
        $clientLatitude = $data['clientLatitude'];
        $clientLongitude = $data['clientLongitude'];

        $iterator = $shipmentPoints->getIterator();

        $iterator->uasort(function ($first, $second) use ($oneDegreeLength, $clientLatitude, $clientLongitude) {
            return ($oneDegreeLength * sqrt((($clientLatitude - $first['Latitude']) ** 2) +
                    (($clientLongitude - $first['Longitude']) ** 2))) >
            ($oneDegreeLength * sqrt((($clientLatitude - $second['Latitude']) ** 2) +
                    (($clientLongitude - $second['Longitude']) ** 2))) ? 1 : -1;
        });

        $sortedShipmentPoints = new ArrayCollection(iterator_to_array($iterator));
        $nearestShipmentPoint = $sortedShipmentPoints->first();

        $response = new Response();
        $response->setContent(json_encode($nearestShipmentPoint, JSON_THROW_ON_ERROR));
        $response->setStatusCode(Response::HTTP_OK);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }


}

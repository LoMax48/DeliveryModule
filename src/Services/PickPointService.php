<?php

namespace App\Services;

use App\Entity\Connection;
use App\Utils\Transformers\CityTransformer;
use App\Utils\Transformers\RegionTransformer;
use Doctrine\Common\Collections\ArrayCollection;
use RetailCrm\Api\Model\Callback\Entity\Delivery\RequestProperty\RequestCalculate;
use RetailCrm\Api\Model\Callback\Response\ErrorResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PickPointService
{
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
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

    public function getShipmentPointsList(Connection $connection, string $targetCity, string $targetRegion): Response
    {
        $response = new JsonResponse();

        try {
            $deliveryResponse = $this->httpClient->request(
                'POST',
                'https://e-solution.pickpoint.ru/apitest/clientpostamatlist',
                [
                    'json' => [
                        'SessionId' => $this->startSession($connection),
                        'IKN' => $connection->getDeliveryIKN(),
                    ]
                ]
            );

            $shipmentPoints = new ArrayCollection($deliveryResponse->toArray());

            if ($targetCity && $targetRegion) {
                $filteredShipmentPoints =
                    $shipmentPoints->filter(function ($shipmentPoint) use ($targetCity, $targetRegion) {
                        return $shipmentPoint['CitiName'] === $targetCity &&
                            $shipmentPoint['Region'] === $targetRegion;
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

    /*public function getNearestShipmentPoint(Connection $connection, string $targetCity): Response
    {
        $shipmentPointsResponse = $this->getShipmentPointsList($connection, $targetCity);
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
    }*/

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
}

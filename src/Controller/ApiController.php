<?php

namespace App\Controller;

use App\Repository\ConnectionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use RetailCrm\Api\Factory\SimpleClientFactory;
use RetailCrm\Api\Interfaces\ApiExceptionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @Route("/api/v1")
 */
class ApiController extends AbstractController
{
    private $httpClient;

    public function __construct(HttpClientInterface $client)
    {
        $this->httpClient = $client;
    }

    public function startSession(string $login, string $password): string
    {
        try {
            $response = $this->httpClient->request(
                'POST',
                'https://e-solution.pickpoint.ru/apitest/login',
                [
                    'json' => [
                        'Login' => $login,
                        'Password' => $password
                    ]
                ]
            );

            $content = $response->toArray();

            return $content['SessionId'] ?: $content['ErrorMessage'];
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
    }



    /**
     * @return Response
     * @Route("/shipmentPointList", name="api_postamat_list", methods={"GET"})
     */
    public function getShipmentPointsList(Request $request, ConnectionRepository $connectionRepository): Response
    {
        $data = json_decode($request->getContent(), true);

        $connection = $connectionRepository->findOneBy([
            'deliveryLogin' => $data['clientId']
        ]);

        $response = new Response();

        if ($connection) {
            try {
                $deliveryResponse = $this->httpClient->request(
                    'POST',
                    'https://e-solution.pickpoint.ru/apitest/clientpostamatlist',
                    [
                        'json' => [
                            'SessionId' => $this->startSession(
                                $connection->getDeliveryLogin(),
                                $connection->getDeliveryPassword()
                            ),
                            'IKN' => $connection->getDeliveryIKN()
                        ]
                    ]
                );

                $targetCity = $data['city'];

                $shipmentPoints = new ArrayCollection($deliveryResponse->toArray());

                $filteredShipmentPoints = $shipmentPoints->filter(function ($shipmentPoint) use ($targetCity) {
                    return $shipmentPoint['CitiName'] === $targetCity;
                });

                $response->setContent(json_encode($filteredShipmentPoints->getValues()));
            } catch (\Exception $exception) {
                $response->setContent(json_encode([
                    'errorMessage' => $exception->getMessage()
                ]));
            }
        } else {
            $response->setContent(json_encode([
                'errorMessage' => 'Неверный логин службы доставки'
            ]));
        }

        $response->setStatusCode(Response::HTTP_OK);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * @return Response
     * @Route("/nearestShipmentPoint", name="api_nearest_postamat", methods={"POST"})
     */
    public function getNearestShipmentPoint(Request $request, ConnectionRepository $connectionRepository): Response
    {
        $data = json_decode($request->getContent(), true);

        $shipmentPointsResponse = $this->getShipmentPointsList($request, $connectionRepository);
        $shipmentPoints = new ArrayCollection(json_decode($shipmentPointsResponse->getContent(), true));

        $iterator = $shipmentPoints->getIterator();

        $oneDegreeLength = 80000;
        $clientLatitude = $data['clientLatitude'];
        $clientLongitude = $data['clientLongitude'];

        $iterator->uasort(function ($first, $second) use ($oneDegreeLength, $clientLatitude, $clientLongitude) {
            return ($oneDegreeLength * sqrt((($clientLatitude - $first['Latitude']) ** 2) +
                    (($clientLongitude - $first['Longitude']) ** 2))) >
            ($oneDegreeLength * sqrt((($clientLatitude - $second['Latitude']) ** 2) +
                    (($clientLongitude - $second['Longitude']) ** 2))) ? 1 : -1;
        });

        $sortedShipmentPoints = new ArrayCollection(iterator_to_array($iterator));
        $nearestShipmentPoint = $sortedShipmentPoints->first();

        $response = new Response();
        $response->setContent(json_encode($nearestShipmentPoint));
        $response->setStatusCode(Response::HTTP_OK);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * @return Response
     * @Route("createshipment", name="api_create_shipment", methods={"POST"})
     */
    public function createShipment(Request $request): Response
    {
    }

    /**
     * @Route("/getsession", name="api_session", methods={"GET"})
     */
    public function getSessionId(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        try {
            $answer = [
                'SessionId' => $this->startSession($data['Login'], $data['Password']) ?: null
            ];
        } catch (\Exception $exception) {
            $answer = [
                'errorMessage' => $exception->getMessage()
            ];
        }

        $response = new Response();
        $response->setStatusCode(Response::HTTP_OK);
        $response->setContent(json_encode($answer));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * @return Response
     * @Route("/credentials", name="api_credentials", methods={"GET"})
     */
    public function credentials(): Response
    {
        $response = new Response();

        $client = SimpleClientFactory::createClient('https://lomax48.retailcrm.ru', '5fdZEgnP0AHQzYtG5CPnKykCRujQBVNK');

        try {
            $apiResponse = $client->api->credentials();
            $response->setContent(json_encode($apiResponse));
            $response->setStatusCode(Response::HTTP_OK);
        } catch (ApiExceptionInterface $exception) {
            $data = [
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => $exception->getMessage(),
            ];
            $response->setContent(json_encode($data));
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
        }

        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }


}

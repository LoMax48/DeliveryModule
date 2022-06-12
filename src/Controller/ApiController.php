<?php

namespace App\Controller;

use App\Component\Exception\AlreadyExistsException;
use App\Entity\Connection;
use App\Repository\ConnectionRepository;
use App\Services\ConnectionService;
use App\Services\IntegrationService;
use App\Services\CallbackService;
use App\Utils\ConfigurationBuilder;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RetailCrm\Api\Factory\SimpleClientFactory;
use RetailCrm\Api\Interfaces\ApiExceptionInterface;
use RetailCrm\Api\Model\Callback\Entity\Delivery\RequestProperty\RequestCalculate;
use RetailCrm\Api\Model\Callback\Entity\Delivery\RequestProperty\RequestSave;
use RetailCrm\Api\Model\Entity\Integration\IntegrationModule;
use RetailCrm\Api\Model\Entity\Integration\Integrations;
use RetailCrm\Api\Model\Request\Integration\IntegrationModulesEditRequest;
use RetailCrm\Api\Model\Response\Integration\IntegrationModulesEditResponse;
use RetailCrm\ServiceBundle\Models\Error;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @Route("/api/v1")
 */
class ApiController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private HttpClientInterface $httpClient;
    private CallbackService $service;


    public function __construct(
        HttpClientInterface $httpClient,
        EntityManagerInterface $entityManager,
        CallbackService $service
    ) {
        $this->httpClient = $httpClient;
        $this->entityManager = $entityManager;
        $this->service = $service;
    }

//    /**
//     * @Route("/signin", name="api_connection_create", options = { "expose" = true }, methods={"POST"})
//     */
//    public function signIn(Connection $connectionData): Response
//    {
//        try {
//            $connection = $this->connectionService->createConnection($connectionData);
//        } catch (AlreadyExistsException $exception) {
//            $apiResponse = new Error();
//            $apiResponse->code = 'ACCOUNT_ALREADY_EXISTS_ERROR';
//            $apiResponse->message = 'This connection already exists';
//
//            return new JsonResponse($apiResponse, Response::HTTP_NOT_FOUND);
//        }
//
//        try {
//            $this->integrationService->createOrUpdate($connection);
//        } catch (\Throwable $exception) {
//            return new JsonResponse(['success' => false], Response::HTTP_INTERNAL_SERVER_ERROR);
//        }
//
//        $this->entityManager->flush();
//
//        return new JsonResponse(['success' => true]);
//    }

    public function startSession(string $login, string $password): string
    {
        try {
            $response = $this->httpClient->request(
                'POST',
                'https://e-solution.pickpoint.ru/apitest/login',
                [
                    'json' => [
                        'Login' => $login,
                        'Password' => $password,
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


    /**
     * @return Response
     * @Route("/nearestShipmentPoint", name="api_nearest_postamat", methods={"GET"})
     */

    /**
     * @return Response
     * @Route("/registermodule", name="api_register_module", methods={"POST"})
     */
    public function registerModule(ConfigurationBuilder $configurationBuilder): Response
    {
        $connection = $this->entityManager->getRepository(Connection::class)->findOneBy([
            'crmUrl' => 'https://lomax48.retailcrm.ru',
        ]);

        if ($connection !== null) {
            $client = SimpleClientFactory::createClient($connection->getCrmUrl(), $connection->getApiKey());
            $module = $configurationBuilder->build($connection);
            try {
                $integrationResponse = $client->integration->edit(
                    'pickpoint',
                    new IntegrationModulesEditRequest($module)
                );
                $response = new JsonResponse([
                    'success' => $integrationResponse->success
                ]);
            } catch (ApiExceptionInterface $exception) {
                $response = new JsonResponse([
                    'code' => $exception->getCode(),
                    'message' => $exception->getMessage(),
                ]);
            }
        }
        return $response;
    }

    /**
     * @param RequestCalculate $requestCalculate
     * @return Response
     * @throws \JsonException
     * @Route("/calculate", name="api_calculate", methods={"POST"})
     */
    public function calculate(RequestCalculate $requestCalculate): Response
    {
        $connection = $this->entityManager->getRepository(Connection::class)->findOneBy([
            'crmUrl' => 'https://lomax48.retailcrm.ru',
        ]);

        $result = $this->service->calculate($connection, $requestCalculate);

        return new JsonResponse($result);
    }

    public function save(RequestSave $requestSave): Response
    {
        $connection = $this->entityManager->getRepository(Connection::class)->findOneBy([
            'crmUrl' => 'https://lomax48.retailcrm.ru',
        ]);

        $result = $this->service->save($connection, $requestSave);

        return new JsonResponse($result);
    }
}

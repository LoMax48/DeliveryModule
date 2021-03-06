<?php

namespace App\Controller;

use App\Entity\Connection;
use App\Services\CallbackService;
use App\Utils\ConfigurationBuilder;
use Doctrine\ORM\EntityManagerInterface;
use RetailCrm\Api\Factory\SimpleClientFactory;
use RetailCrm\Api\Interfaces\ApiExceptionInterface;
use RetailCrm\Api\Model\Callback\Entity\Delivery\RequestProperty\RequestCalculate;
use RetailCrm\Api\Model\Callback\Entity\Delivery\RequestProperty\RequestDelete;
use RetailCrm\Api\Model\Callback\Entity\Delivery\RequestProperty\RequestPrint;
use RetailCrm\Api\Model\Callback\Entity\Delivery\RequestProperty\RequestSave;
use RetailCrm\Api\Model\Request\Delivery\DeliveryShipmentsRequest;
use RetailCrm\Api\Model\Request\Integration\IntegrationModulesEditRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api/v1")
 */
class ApiController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private CallbackService $service;

    public function __construct(EntityManagerInterface $entityManager, CallbackService $service)
    {
        $this->entityManager = $entityManager;
        $this->service = $service;
    }

    /**
     * @param ConfigurationBuilder $configurationBuilder
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

    /**
     * @param RequestSave $requestSave
     * @return Response
     * @Route("/save", name="api_save", methods={"POST"})
     */
    public function save(RequestSave $requestSave): Response
    {
        $connection = $this->entityManager->getRepository(Connection::class)->findOneBy([
            'crmUrl' => 'https://lomax48.retailcrm.ru',
        ]);

        if ($requestSave->deliveryId === null) {
            $result = $this->service->save($connection, $requestSave);
        } else {
            $result = $this->service->update($connection, $requestSave);
        }
        return new JsonResponse($result);
    }

    /**
     * @param RequestDelete $requestDelete
     * @return JsonResponse
     * @Route("/delete", name="api_delete", methods={"POST"})
     */
    public function delete(RequestDelete $requestDelete)
    {
        $connection = $this->entityManager->getRepository(Connection::class)->findOneBy([
            'crmUrl' => 'https://lomax48.retailcrm.ru',
        ]);

        $result = $this->service->delete($connection, $requestDelete);

        return new JsonResponse($result);
    }

    /**
     * @param RequestPrint $requestPrint
     * @return Response
     * @Route("/print", name="api_print", methods={"POST"})
     */
    public function print(RequestPrint $requestPrint): Response
    {
        $connection = $this->entityManager->getRepository(Connection::class)->findOneBy([
            'crmUrl' => 'https://lomax48.retailcrm.ru',
        ]);

        $result = $this->service->makeLabel($connection, $requestPrint);

        return new Response($result);
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/shipmentPointList", name="api_shipment_points", methods={"GET"})
     */
    public function shipmentPointList(Request $request): Response
    {
        $connection = $this->entityManager->getRepository(Connection::class)->findOneBy([
            'crmUrl' => 'https://lomax48.retailcrm.ru',
        ]);

        $result = $this->service->shipmentPointList($connection, $request);

        return new JsonResponse($result);
    }
}

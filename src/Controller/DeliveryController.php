<?php

namespace App\Controller;

use App\Repository\ConnectionRepository;
use App\Repository\DeliveryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/delivery")
 */
class DeliveryController extends AbstractController
{
    /**
     * @Route("/", name="app_delivery_index", methods={"GET"})
     */
    public function index(DeliveryRepository $deliveryRepository, ConnectionRepository $connectionRepository): Response
    {
        return $this->render('delivery/index.html.twig', [
            'deliveries' => $deliveryRepository->findBy([
                'connection' => $connectionRepository->findOneBy([
                    'crmUrl' => 'https://lomax48.retailcrm.ru',
                ]),
            ]),
            'avgSum' => $this->getAverageDeliveryCost($deliveryRepository, $connectionRepository),
        ]);
    }

    public function getAverageDeliveryCost(
        DeliveryRepository $deliveryRepository,
        ConnectionRepository $connectionRepository
    ): float {
        $sum = 0;
        $count = 0;

        $deliveries = $deliveryRepository->findBy([
            'connection' => $connectionRepository->findOneBy([
                'crmUrl' => 'https://lomax48.retailcrm.ru',
            ]),
        ]);

        foreach ($deliveries as $delivery) {
            $sum += $delivery->getSum();
            $count++;
        }

        return $sum / $count;
    }
}

<?php

namespace App\EventSubscriber;

use RetailCrm\ServiceBundle\Exceptions\InvalidRequestArgumentException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ExceptionSubscriber implements EventSubscriberInterface
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        $errors = [];


        $context = json_encode([
            'success' => false,
            'code' => 'ERROR',
            'message' => $exception->getMessage(),
        ], JSON_THROW_ON_ERROR);
        $event->setResponse(new Response($context, Response::HTTP_BAD_REQUEST));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.exception' => 'onKernelException',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Répond aux prévols CORS (OPTIONS) sur /api sans 404.
 */
class ApiOptionsSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 252],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        if ('OPTIONS' !== $request->getMethod()) {
            return;
        }
        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/api')) {
            return;
        }
        $event->setResponse(new Response('', Response::HTTP_NO_CONTENT));
    }
}

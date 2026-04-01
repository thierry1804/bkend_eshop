<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CorsSubscriber implements EventSubscriberInterface
{
    private string $allowedOrigin;

    public function __construct(?string $allowedOrigin = null)
    {
        $this->allowedOrigin = $allowedOrigin ?? 'http://localhost:3000';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $request = $event->getRequest();

        // Handle preflight requests
        if ($request->getMethod() === 'OPTIONS') {
            $response->setStatusCode(200);
        }

        $origin = $request->headers->get('Origin');
        $allowOrigin = $this->resolveAllowedOrigin($origin);
        if (null !== $allowOrigin) {
            $response->headers->set('Access-Control-Allow-Origin', $allowOrigin);
            $response->headers->set('Vary', 'Origin', false);
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, x-api-key, X-API-Key');
        $response->headers->set('Access-Control-Expose-Headers', 'Link');
        $response->headers->set('Access-Control-Max-Age', '3600');

        if ($request->getMethod() === 'OPTIONS') {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }
    }

    /**
     * CORS n'accepte pas une regex dans Access-Control-Allow-Origin : il faut renvoyer
     * l'origine exacte du navigateur si elle est autorisée (liste ou motif PREG).
     */
    private function resolveAllowedOrigin(?string $origin): ?string
    {
        if (null === $origin || '' === $origin) {
            return null;
        }

        $configured = trim($this->allowedOrigin);

        if (str_starts_with($configured, '^')) {
            $ok = @preg_match('#'.$configured.'#', $origin) === 1;

            return $ok ? $origin : null;
        }

        if (str_contains($configured, ',')) {
            foreach (array_map('trim', explode(',', $configured)) as $allowed) {
                if ($allowed === $origin) {
                    return $origin;
                }
            }

            return null;
        }

        return $configured === $origin ? $origin : null;
    }
}


<?php

namespace App\Controller;

use App\DTO\ContactMailDTO;
use App\Message\SendContactMailMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ContactMailController extends AbstractController
{
    public function __construct(
        private RateLimiterFactory $contactMailLimiter
    ) {}

    #[Route('/api/mail/contact', methods: ['POST'])]
    public function __invoke(
        Request $request,
        ValidatorInterface $validator,
        MessageBusInterface $bus
    ): JsonResponse {
        // Rate limiting
        $limiter = $this->contactMailLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return $this->json([
                'error' => 'Too many requests. Please try again later.'
            ], 429);
        }

        $data = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json([
                'error' => 'Invalid JSON'
            ], 400);
        }

        $dto = new ContactMailDTO();
        $dto->email = $data['email'] ?? '';
        $dto->message = $data['message'] ?? '';

        $errors = $validator->validate($dto);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json([
                'errors' => $errorMessages
            ], 400);
        }

        $bus->dispatch(new SendContactMailMessage($dto));

        return $this->json([
            'status' => 'accepted'
        ], 202);
    }
}


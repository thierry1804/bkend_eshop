<?php

namespace App\MessageHandler;

use App\Message\SendContactMailMessage;
use App\Service\MailService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendContactMailHandler
{
    public function __construct(
        private MailService $mailService
    ) {}

    public function __invoke(SendContactMailMessage $message): void
    {
        $dto = $message->dto;

        $this->mailService->sendContactMail(
            $dto->email,
            $dto->message
        );
    }
}


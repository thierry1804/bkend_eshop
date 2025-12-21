<?php

namespace App\Message;

use App\DTO\ContactMailDTO;

class SendContactMailMessage
{
    public function __construct(
        public readonly ContactMailDTO $dto
    ) {}
}


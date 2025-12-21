<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ContactMailDTO
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;

    #[Assert\NotBlank]
    #[Assert\Length(min: 10, max: 2000)]
    public string $message;
}


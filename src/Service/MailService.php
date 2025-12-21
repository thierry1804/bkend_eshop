<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class MailService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig
    ) {}

    public function sendContactMail(string $from, string $message): void
    {
        $html = $this->twig->render('emails/contact.html.twig', [
            'email' => $from,
            'message' => $message,
        ]);

        $email = (new Email())
            ->from('no-reply@eshopbyvalsue.mg')
            ->to('trandriantiana@icloud.com')
            ->replyTo($from)
            ->subject('📩 Nouveau message de contact')
            ->html($html);

        $this->mailer->send($email);
    }
}


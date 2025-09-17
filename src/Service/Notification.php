<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class Notification
{
    public function __construct(
        private MailerInterface $mailer,
        private string $fromAddress,
        private string $fromName = 'OrangeChef'
    ) {}

    /**
     * Envoi simple (texte + HTML optionnel).
     */
    public function send(string $to, string $subject, string $text, ?string $html = null): void
    {
        $email = (new Email())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to($to)
            ->subject($subject)
            ->text($text);

        if ($html !== null) {
            $email->html($html);
        }

        $this->mailer->send($email);
    }

    /**
     * Envoi basé sur des templates Twig (HTML et texte optionnel).
     */
    public function sendTemplate(
        string $to,
        string $subject,
        string $htmlTemplate,
        array $context = [],
        ?string $textTemplate = null,
        ?string $replyTo = null,
        array $cc = [],
        array $bcc = [],
        array $attachments = [] // chemins fichiers
    ): void {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to($to)
            ->subject($subject)
            ->htmlTemplate($htmlTemplate)
            ->context($context);

        if ($textTemplate) $email->textTemplate($textTemplate);
        if ($replyTo) $email->replyTo($replyTo);
        if ($cc) $email->cc(...$cc);
        if ($bcc) $email->bcc(...$bcc);
        foreach ($attachments as $path) $email->attachFromPath($path);

        $this->mailer->send($email);
    }

}

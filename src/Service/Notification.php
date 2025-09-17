<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class Notification
{
    /**
     * On injecte le service Mailer + l’adresse d’expéditeur (et son nom).
     * -> $fromAddress / $fromName viennent de config/services.yaml (parameters).
     */
    public function __construct(
        private MailerInterface $mailer,
        private string $fromAddress,
        private string $fromName = 'OrangeChef'
    ) {}

    /**
     * ENVOI SIMPLE
     * - $to       : destinataire (email)
     * - $subject  : sujet
     * - $text     : version texte (obligatoire)
     * - $html     : version HTML (optionnelle)
     *
     * Si $html est fourni, l’email sera multipart (texte + HTML).
     */
    public function send(string $to, string $subject, string $text, ?string $html = null): void
    {
        // 1) On construit l’email de base
        $email = (new Email())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to($to)
            ->subject($subject)
            ->text($text); // toujours une version texte (lisible par tous)

        // 2) Optionnel : si on a une version HTML, on l’ajoute
        if ($html !== null) {
            $email->html($html);
        }

        // 3) Envoi
        $this->mailer->send($email);
    }

    /**
     * ENVOI AVEC TEMPLATE TWIG
     * - $htmlTemplate : chemin du template HTML (ex: 'mail/demo.html.twig')
     * - $context      : données passées au template Twig (ex: ['user' => $user])
     * - $textTemplate : template texte (optionnel). Si tu n’en as pas, laisse null.
     * - $replyTo      : adresse de réponse (optionnelle)
     * - $cc / $bcc    : copies (optionnelles) — tableaux d’emails
     * - $attachments  : pièces jointes (optionnelles) — tableaux de chemins de fichiers
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
        array $attachments = []
    ): void {
        // 1) Email basé sur un template Twig
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to($to)
            ->subject($subject)
            ->htmlTemplate($htmlTemplate)
            ->context($context);

        // 2) Options simples et lisibles
        if ($textTemplate) { $email->textTemplate($textTemplate); }
        if ($replyTo)      { $email->replyTo($replyTo); }
        if (!empty($cc))   { $email->cc(...$cc); }   // ... = “déplie le tableau”
        if (!empty($bcc))  { $email->bcc(...$bcc); }

        // 3) Pièces jointes (si on en a)
        foreach ($attachments as $path) {
            $email->attachFromPath($path);
        }

        // 4) Envoi
        $this->mailer->send($email);
    }
}

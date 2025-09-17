<?php

namespace App\Controller;

use App\Form\ContactType;
use App\Service\Notification;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact', methods: ['GET','POST'])]
    public function index(Request $request, Notification $notification): Response
    {
        // 1) Création + binding du formulaire
        $form = $this->createForm(ContactType::class);
        $form->handleRequest($request);

        // 2) Soumission + validation
        if ($form->isSubmitted() && $form->isValid()) {
            // a) Honeypot : si rempli → spam silencieux
            $honeypot = $form->get('website')->getData();
            if (!empty($honeypot)) {
                $this->addFlash('warning', 'Votre message n\'a pas pu être envoyé.');
                return $this->redirectToRoute('app_contact');
            }

            // b) ⚠️ Rate-limit ultra simple via Session (anti flood : 1 message / 60s)
            $session = $request->getSession(); // SessionInterface
            $lastSent = $session?->get('contact_last_sent'); // timestamp (int) ou null
            if ($lastSent && (time() - $lastSent) < 60) {
                // Moins de 60s depuis le dernier envoi
                $this->addFlash('warning', 'Veuillez patienter avant de renvoyer un message.');
                return $this->redirectToRoute('app_contact');
            }

            // c) Récup données et envoi d’e-mail (admin + accusé utilisateur)
            $data = $form->getData();
            $toAdmin = $_ENV['APP_CONTACT_TO_ADDRESS'] ?? 'contact@orangechef.com';
            try {
                // Email admin (reçois le message)
                $notification->sendTemplate(
                    to: $toAdmin,
                    subject: '📨 Nouveau message de contact',
                    htmlTemplate: 'email/contact_admin.html.twig',
                    context: [
                        'name'    => $data['name']    ?? 'Anonyme',
                        'senderEmail'   => $data['email']   ?? 'inconnu',
                        'userSubject' => $data['subject'] ?? '(Sans sujet)',
                        'userMessage' => $data['message'] ?? '',
                    ],
                    textTemplate: 'email/contact_admin.txt.twig',
                    replyTo: $data['email'] ?? null
                );

                // Accusé de réception (facultatif) si email saisi
                if (!empty($data['email'])) {
                    $notification->sendTemplate(
                        to: $data['email'],
                        subject: '📬 Nous avons bien reçu votre message',
                        htmlTemplate: 'email/contact_user_reply.html.twig',
                        context: [
                            'name'    => $data['name']    ?? 'cher visiteur',
                            'userSubject' => $data['subject'] ?? '',
                        ],
                        textTemplate: 'email/contact_user_reply.txt.twig'
                    );
                }

                // d) ✅ Envoi réussi → on “arme” l’anti-flood pour 60s
                $session?->set('contact_last_sent', time());

                $this->addFlash('success', 'Merci, votre message a bien été envoyé.');
            } catch (\Throwable $e) {
                // On ne bloque pas l’utilisateur si l’email tombe en panne
                $this->addFlash('warning', "Message reçu, mais l'e-mail n'a pas pu être envoyé : ".$e->getMessage());
            }

            // PRG pour éviter la re-soumission
            return $this->redirectToRoute('app_contact');
        }

        // 3) Affichage du formulaire (GET initial ou POST invalide)
        return $this->render('contact/index.html.twig', [
            'form' => $form->createView(), //  en Twig, on passe la "vue" du formulaire
        ]);
    }
}

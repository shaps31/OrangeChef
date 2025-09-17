<?php
// src/Controller/V2/PingController.php
namespace App\Controller\V2;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class PingController
{
    #[Route('/v2/ping', name: 'v2_ping', methods: ['GET'])]
    public function __invoke(): Response
    {
        return new Response('pong', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}

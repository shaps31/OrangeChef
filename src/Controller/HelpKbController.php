<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\KernelInterface;

final class HelpKbController
{
    public function __construct(
        private UrlGeneratorInterface $urls,
        private KernelInterface $kernel
    ) {}

    #[Route('/help/kb.json', name: 'help_kb', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        // 1) Fichier KB attendu
        $jsonPath = $this->kernel->getProjectDir() . '/public/help_kb.json';

        // 2) Données par défaut (si le fichier n'existe pas)
        $data = [
            'categories' => [[
                'id' => 'gen',
                'label' => 'Général',
                'items' => [
                    ['q' => 'Bienvenue', 'a' => "Utilisez la recherche pour trouver de l’aide."]
                ],
            ]],
        ];

        // 3) Si le fichier est présent et lisible, on tente de le lire
        if (is_file($jsonPath) && is_readable($jsonPath)) {
            try {
                $raw = file_get_contents($jsonPath) ?: '';
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded) && isset($decoded['categories'])) {
                    $data = $decoded;
                }
            } catch (\Throwable) {
                // on garde le fallback en cas d'erreur de JSON
            }
        }

        // 4) Préfixe de langue simple: /fr, /en, etc. (on ignore fr_FR)
        $locale = (string) $request->getLocale();
        $prefix = (strlen($locale) === 2) ? '/' . $locale : '';

        // 5) Normaliser les href des items (internes, externes, routes)
        foreach ($data['categories'] ?? [] as &$category) {
            foreach ($category['items'] ?? [] as &$item) {
                $href = $item['href'] ?? null;

                // pas d'href → on supprime juste la clé
                if (!is_string($href) || $href === '') {
                    unset($item['href']);
                    continue;
                }

                // externes/mail/tel → on laisse comme c'est
                if (preg_match('~^(https?:)?//~i', $href) || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                    continue;
                }

                // href sous forme "route:nom_de_route"
                if (str_starts_with($href, 'route:')) {
                    $route = trim(substr($href, 6));
                    try {
                        $item['href'] = $this->urls->generate($route, ['_locale' => $locale], UrlGeneratorInterface::ABSOLUTE_PATH);
                    } catch (\Throwable) {
                        unset($item['href']); // route inconnue → on supprime
                    }
                    continue;
                }

                // chemin relatif/interne : on force le leading slash
                $path = ($href[0] ?? '') === '/' ? $href : '/' . $href;

                // on préfixe par la locale si fournie et pas déjà présente
                if ($prefix && !str_starts_with($path, $prefix . '/')) {
                    $path = $prefix . $path;
                }

                $item['href'] = $path;
            }
        }
        unset($category, $item);

        // 6) Encodage JSON "lisible" (pas d'escape inutile)
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // 7) Réponse simple + cache public léger (pas d’ETag pour rester “débutant”)
        $response = new Response($json ?: '{}', Response::HTTP_OK, [
            'Content-Type' => 'application/json; charset=utf-8',
        ]);
        $response->setPublic();
        $response->setMaxAge(300); // 5 minutes

        return $response;
    }
}

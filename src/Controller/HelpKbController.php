<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
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
        $jsonPath = $this->kernel->getProjectDir().'/public/help_kb.json';

        // Fallback minimal
        $data = ['categories' => [[
            'id' => 'gen', 'label' => 'Général',
            'items' => [['q' => 'Bienvenue', 'a' => "Utilisez la recherche pour trouver de l’aide."]],
        ]]];

        if (is_file($jsonPath) && is_readable($jsonPath)) {
            try {
                $raw = file_get_contents($jsonPath) ?: '';
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded) && isset($decoded['categories'])) {
                    $data = $decoded;
                }
            } catch (\Throwable) { /* fallback */ }
        }

        $locale = (string) $request->getLocale();
        $prefix = preg_match('~^[a-z]{2}$~i', $locale) ? '/'.$locale : '';

        // Normalisation des href (sans métas)
        foreach ($data['categories'] ?? [] as &$cat) {
            foreach ($cat['items'] ?? [] as &$it) {
                $href = $it['href'] ?? null;
                if (!is_string($href) || $href === '') { unset($it['href']); continue; }

                if (preg_match('~^(https?:)?//~i', $href) || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                    continue;
                }
                if (str_starts_with($href, 'route:')) {
                    $route = trim(substr($href, 6));
                    try {
                        $it['href'] = $this->urls->generate($route, ['_locale' => $locale], UrlGeneratorInterface::ABSOLUTE_PATH);
                    } catch (\Throwable) {
                        unset($it['href']);
                    }
                    continue;
                }
                $path = $href[0] === '/' ? $href : '/'.$href;
                if ($prefix && !str_starts_with($path, $prefix.'/')) {
                    $path = $prefix.$path;
                }
                $it['href'] = $path;
            }
        }
        unset($cat, $it);

        $json = json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

        $response = new Response($json, 200, ['Content-Type' => 'application/json; charset=utf-8']);
        // Cache soft (tu peux ajuster ou enlever si tu préfères)
        $response->setPublic();
        $response->setMaxAge(300);
        $response->setSharedMaxAge(300);
        $response->setEtag(sha1($json));
        if ($response->isNotModified($request)) {
            return $response;
        }
        return $response;
    }
}

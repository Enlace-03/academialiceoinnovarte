<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

/**
 * Detección pura de enlaces de YouTube (Hito 3b-3, adjuntos de entrega):
 * sin I/O, sin llamada a la API de YouTube -- solo reconoce las formas
 * estándar de URL y extrae el id del video para armar la URL de embed.
 * Cualquier otro dominio simplemente no es YouTube, se muestra como enlace
 * simple en la UI. Reutilizable para el futuro hito de video en galería
 * (TODO.md #26).
 */
final class YoutubeUrlDetector
{
    /**
     * @return array{isYoutube: bool, embedUrl: ?string}
     */
    public static function detect(string $url): array
    {
        $videoId = self::extractVideoId(trim($url));

        if ($videoId === null) {
            return ['isYoutube' => false, 'embedUrl' => null];
        }

        return ['isYoutube' => true, 'embedUrl' => "https://www.youtube.com/embed/{$videoId}"];
    }

    private static function extractVideoId(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            return null;
        }

        $host = strtolower(preg_replace('/^www\./', '', $parts['host']));
        $path = $parts['path'] ?? '';

        if (! in_array($host, ['youtube.com', 'youtu.be', 'm.youtube.com'], true)) {
            return null;
        }

        if ($host === 'youtu.be') {
            $id = ltrim($path, '/');

            return self::isValidVideoId($id) ? $id : null;
        }

        if (preg_match('#^/(?:embed|shorts)/([A-Za-z0-9_-]{11})#', $path, $matches) === 1) {
            return $matches[1];
        }

        if ($path === '/watch') {
            parse_str($parts['query'] ?? '', $query);
            $id = $query['v'] ?? null;

            return is_string($id) && self::isValidVideoId($id) ? $id : null;
        }

        return null;
    }

    private static function isValidVideoId(string $id): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1;
    }
}

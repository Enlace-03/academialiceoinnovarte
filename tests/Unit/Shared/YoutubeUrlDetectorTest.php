<?php

namespace Tests\Unit\Shared;

use App\Modules\Shared\Support\YoutubeUrlDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class YoutubeUrlDetectorTest extends TestCase
{
    public static function youtubeUrlProvider(): array
    {
        return [
            'watch with www' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'watch without www, extra query params' => ['https://youtube.com/watch?v=dQw4w9WgXcQ&t=10s', 'dQw4w9WgXcQ'],
            'youtu.be short link' => ['https://youtu.be/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'embed link' => ['https://www.youtube.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'shorts link' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
        ];
    }

    #[DataProvider('youtubeUrlProvider')]
    public function test_detects_common_youtube_url_variants(string $url, string $expectedVideoId): void
    {
        $result = YoutubeUrlDetector::detect($url);

        $this->assertTrue($result['isYoutube']);
        $this->assertSame("https://www.youtube.com/embed/{$expectedVideoId}", $result['embedUrl']);
    }

    public static function nonYoutubeUrlProvider(): array
    {
        return [
            'other video platform' => ['https://vimeo.com/12345'],
            'non-youtube domain reusing a v param' => ['https://example.com/watch?v=dQw4w9WgXcQ'],
            'plain text, not a url' => ['not a url at all'],
        ];
    }

    #[DataProvider('nonYoutubeUrlProvider')]
    public function test_does_not_flag_non_youtube_urls(string $url): void
    {
        $result = YoutubeUrlDetector::detect($url);

        $this->assertFalse($result['isYoutube']);
        $this->assertNull($result['embedUrl']);
    }
}

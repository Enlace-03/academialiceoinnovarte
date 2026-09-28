<?php

namespace Tests\Unit\Assessment;

use App\Modules\Assessment\Support\ReportCardScale;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportCardScaleTest extends TestCase
{
    public static function levelProvider(): array
    {
        return [
            'inicio -> 2 (Bajo)' => ['inicio', 2],
            'en_proceso -> 3 (Básico)' => ['en_proceso', 3],
            'logro_esperado -> 4 (Alto)' => ['logro_esperado', 4],
            'logro_destacado -> 5 (Superior)' => ['logro_destacado', 5],
        ];
    }

    #[DataProvider('levelProvider')]
    public function test_converts_each_rubric_level_to_its_number(string $levelKey, int $expected): void
    {
        $this->assertSame($expected, ReportCardScale::fromLevelKey($levelKey));
    }

    public function test_one_is_the_absent_case_and_no_rubric_level_produces_it(): void
    {
        $this->assertSame(1, ReportCardScale::ABSENT);

        foreach (['inicio', 'en_proceso', 'logro_esperado', 'logro_destacado'] as $levelKey) {
            $this->assertNotSame(ReportCardScale::ABSENT, ReportCardScale::fromLevelKey($levelKey));
        }
    }

    public function test_an_unknown_level_key_throws_instead_of_guessing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ReportCardScale::fromLevelKey('superior');
    }
}

<?php

namespace Tests\Feature\Shared;

use Tests\TestCase;

/**
 * <x-progress-stars>: 10 estrellas, cada una vale 10% de $percent, con
 * relleno parcial (no redondeado a estrella completa). Se prueba el HTML
 * renderizado directamente (sin Livewire) contando cuántos overlays de
 * relleno hay y a qué ancho -- style="width: 100%" por cada estrella llena,
 * style="width: N%" (0 < N < 100) para la parcial, ninguno para las vacías.
 */
class ProgressStarsComponentTest extends TestCase
{
    private function render(int $percent): string
    {
        return view('components.progress-stars', ['percent' => $percent])->render();
    }

    private function countOccurrences(string $html, string $needle): int
    {
        return substr_count($html, $needle);
    }

    public function test_zero_percent_renders_no_filled_stars(): void
    {
        $html = $this->render(0);

        $this->assertSame(0, $this->countOccurrences($html, 'style="width:'));
        $this->assertStringContainsString('aria-label="0% de avance"', $html);
    }

    public function test_forty_seven_percent_renders_four_full_stars_and_one_seventy_percent_partial(): void
    {
        $html = $this->render(47);

        $this->assertSame(4, $this->countOccurrences($html, 'style="width: 100%"'));
        $this->assertSame(1, $this->countOccurrences($html, 'style="width: 70%"'));
        $this->assertSame(5, $this->countOccurrences($html, 'style="width:'));
        $this->assertStringContainsString('aria-label="47% de avance"', $html);
    }

    public function test_hundred_percent_renders_ten_full_stars_with_no_partial(): void
    {
        $html = $this->render(100);

        $this->assertSame(10, $this->countOccurrences($html, 'style="width: 100%"'));
        $this->assertSame(10, $this->countOccurrences($html, 'style="width:'));
    }

    /**
     * Caso de borde: 5% no llega a llenar ni una estrella completa -- la
     * primera estrella queda a la mitad, ninguna otra tiene relleno.
     */
    public function test_five_percent_renders_only_a_half_filled_first_star(): void
    {
        $html = $this->render(5);

        $this->assertSame(0, $this->countOccurrences($html, 'style="width: 100%"'));
        $this->assertSame(1, $this->countOccurrences($html, 'style="width: 50%"'));
    }

    /**
     * Caso de borde: 95% deja nueve estrellas llenas y la décima a la mitad
     * -- nunca redondea a las 10 completas.
     */
    public function test_ninety_five_percent_renders_nine_full_stars_and_one_half(): void
    {
        $html = $this->render(95);

        $this->assertSame(9, $this->countOccurrences($html, 'style="width: 100%"'));
        $this->assertSame(1, $this->countOccurrences($html, 'style="width: 50%"'));
    }

    public function test_percent_above_hundred_is_clamped_to_ten_full_stars(): void
    {
        $html = $this->render(140);

        $this->assertSame(10, $this->countOccurrences($html, 'style="width: 100%"'));
        $this->assertStringContainsString('aria-label="100% de avance"', $html);
    }

    public function test_negative_percent_is_clamped_to_no_filled_stars(): void
    {
        $html = $this->render(-20);

        $this->assertSame(0, $this->countOccurrences($html, 'style="width:'));
        $this->assertStringContainsString('aria-label="0% de avance"', $html);
    }
}

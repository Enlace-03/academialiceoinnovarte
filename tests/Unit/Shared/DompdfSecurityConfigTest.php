<?php

namespace Tests\Unit\Shared;

use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

/**
 * Los PDF (boletines) llevan datos de menores: sin JavaScript embebido, sin
 * recursos remotos y sin PHP. Fija la configuración publicada en
 * config/dompdf.php para que un cambio accidental rompa el test.
 */
class DompdfSecurityConfigTest extends TestCase
{
    public function test_javascript_remote_resources_and_php_are_disabled(): void
    {
        $this->assertFalse(config('dompdf.options.enable_javascript'));
        $this->assertFalse(config('dompdf.options.enable_remote'));
        $this->assertFalse(config('dompdf.options.enable_php'));
    }

    public function test_the_loaded_dompdf_instance_uses_that_configuration(): void
    {
        $options = Pdf::loadHTML('<p>x</p>')->getDomPDF()->getOptions();

        $this->assertFalse($options->getIsJavascriptEnabled());
        $this->assertFalse($options->getIsRemoteEnabled());
        $this->assertFalse($options->getIsPhpEnabled());
    }
}

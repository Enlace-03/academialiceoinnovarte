<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Segunda capa además de 'mimes:pdf,docx,xlsx,pptx' (esa ya usa detección
 * real por contenido vía fileinfo -- verificado en vivo contra archivos
 * OOXML mínimos reales, no asumido: en este entorno fileinfo distingue
 * correctamente docx/xlsx/pptx de un ZIP genérico gracias al Content-Type
 * declarado en [Content_Types].xml). Esta regla añade lo que 'mimes' no
 * cubre: estructura interna esperada y el caso de macros (vbaProject.bin,
 * lo que convertiría un .docx/.xlsx/.pptx en .docm/.xlsm/.pptm real).
 *
 * La extensión "creída" se determina con guessExtension() (basado en
 * contenido, vía fileinfo), NUNCA con getClientOriginalExtension() (el
 * nombre de archivo lo controla el cliente) -- así un .exe renombrado a
 * .pdf no elige la rama PDF por el nombre, sino por lo que realmente es.
 */
class ValidDocumentUpload implements ValidationRule
{
    private const OOXML_EXPECTED_FOLDER = [
        'docx' => 'word/',
        'xlsx' => 'xl/',
        'pptx' => 'ppt/',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof File) {
            $fail('Archivo inválido.');

            return;
        }

        $path = $value->getRealPath();

        if ($path === false || $path === '') {
            $fail('Archivo inválido.');

            return;
        }

        $extension = $value->guessExtension();

        if ($extension === 'pdf') {
            $this->validatePdf($path, $fail);

            return;
        }

        if ($extension !== null && array_key_exists($extension, self::OOXML_EXPECTED_FOLDER)) {
            $this->validateOoxml($path, $extension, $fail);

            return;
        }

        $fail('El archivo no es un documento válido (PDF, Word, Excel o PowerPoint).');
    }

    private function validatePdf(string $path, Closure $fail): void
    {
        $header = file_get_contents($path, false, null, 0, 5);

        if ($header !== '%PDF-') {
            $fail('El archivo no es un PDF válido.');
        }
    }

    private function validateOoxml(string $path, string $extension, Closure $fail): void
    {
        $zip = new \ZipArchive();

        if ($zip->open($path) !== true) {
            $fail('El archivo no es un documento de Office válido.');

            return;
        }

        $expectedFolder = self::OOXML_EXPECTED_FOLDER[$extension];
        $hasContentTypes = $zip->locateName('[Content_Types].xml') !== false;
        $hasExpectedFolder = false;
        $hasMacro = false;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name === false) {
                continue;
            }

            if (str_starts_with($name, $expectedFolder)) {
                $hasExpectedFolder = true;
            }

            if (str_ends_with(strtolower($name), 'vbaproject.bin')) {
                $hasMacro = true;
            }
        }

        $zip->close();

        if (! $hasContentTypes || ! $hasExpectedFolder) {
            $fail('El archivo no es un documento de Office válido.');

            return;
        }

        if ($hasMacro) {
            $fail('No se permiten documentos con macros.');
        }
    }
}

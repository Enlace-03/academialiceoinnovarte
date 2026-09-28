<?php

namespace Tests\Feature\Student;

use App\Livewire\Student\EvidenceShow;
use App\Models\User;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Assessment\Models\SubmissionAttachment;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Adjuntos type=document (PDF/docx/xlsx/pptx). Los archivos OOXML de estos
 * tests son ZIPs reales mínimos armados con ZipArchive (no bytes al azar
 * disfrazados de docx) -- verificado en vivo antes de escribir
 * ValidDocumentUpload que finfo/Laravel SÍ detectan correctamente estos
 * paquetes reales como docx/xlsx/pptx (no como application/zip genérico) en
 * este entorno.
 */
class EvidenceShowDocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        Storage::fake('local');

        $cycle = Cycle::factory()->create();
        $grade = SchoolGrade::factory()->create(['cycle_id' => $cycle->id]);
        $this->student = User::factory()->create(['school_grade_id' => $grade->id])->assignRole('student');
        $this->project = Project::factory()->create(['cycle_id' => $cycle->id]);

        $this->actingAs($this->student);
    }

    public function test_each_valid_document_type_is_accepted(): void
    {
        $cases = [
            'documento.pdf' => $this->makePdf(),
            'documento.docx' => $this->makeOoxml('docx'),
            'documento.xlsx' => $this->makeOoxml('xlsx'),
            'documento.pptx' => $this->makeOoxml('pptx'),
        ];

        foreach ($cases as $filename => $content) {
            $evidence = ExpectedEvidence::factory()->create(['phase_id' => $this->project->phases()->first()->id]);
            $file = UploadedFile::fake()->createWithContent($filename, $content);

            Livewire::test(EvidenceShow::class, ['project' => $this->project, 'evidence' => $evidence])
                ->set('newDocuments', [$file])
                ->call('submit')
                ->assertHasNoErrors();

            $this->assertDatabaseHas('submission_attachments', [
                'type' => 'document',
                'original_filename' => $filename,
            ]);
        }
    }

    public function test_an_exe_renamed_to_pdf_is_rejected(): void
    {
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $this->project->phases()->first()->id]);
        $file = UploadedFile::fake()->createWithContent('malicioso.pdf', "MZ\x90\x00\x03\x00\x00\x00fake-exe-content");

        Livewire::test(EvidenceShow::class, ['project' => $this->project, 'evidence' => $evidence])
            ->set('newDocuments', [$file])
            ->call('submit')
            ->assertHasErrors('newDocuments.0');

        $this->assertSame(0, Submission::count());
    }

    public function test_a_docx_with_a_macro_is_rejected(): void
    {
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $this->project->phases()->first()->id]);
        $file = UploadedFile::fake()->createWithContent('conmacro.docx', $this->makeOoxml('docx', withMacro: true));

        Livewire::test(EvidenceShow::class, ['project' => $this->project, 'evidence' => $evidence])
            ->set('newDocuments', [$file])
            ->call('submit')
            ->assertHasErrors('newDocuments.0');

        $this->assertSame(0, Submission::count());
    }

    public function test_an_svg_is_rejected_as_a_document(): void
    {
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $this->project->phases()->first()->id]);
        $file = UploadedFile::fake()->createWithContent('imagen.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        Livewire::test(EvidenceShow::class, ['project' => $this->project, 'evidence' => $evidence])
            ->set('newDocuments', [$file])
            ->call('submit')
            ->assertHasErrors('newDocuments.0');

        $this->assertSame(0, Submission::count());
    }

    public function test_the_ninth_attachment_is_rejected(): void
    {
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $this->project->phases()->first()->id]);

        $documents = array_map(
            fn (int $i) => UploadedFile::fake()->createWithContent("documento-{$i}.pdf", $this->makePdf()),
            range(1, 9),
        );

        Livewire::test(EvidenceShow::class, ['project' => $this->project, 'evidence' => $evidence])
            ->set('newDocuments', $documents)
            ->call('submit')
            ->assertHasErrors('attachments');

        $this->assertSame(0, Submission::count());
    }

    public function test_another_student_cannot_download_the_attachment_and_it_is_served_as_an_attachment(): void
    {
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $this->project->phases()->first()->id]);
        $file = UploadedFile::fake()->createWithContent('documento.pdf', $this->makePdf());

        Livewire::test(EvidenceShow::class, ['project' => $this->project, 'evidence' => $evidence])
            ->set('newDocuments', [$file])
            ->call('submit')
            ->assertHasNoErrors();

        $attachment = SubmissionAttachment::where('type', 'document')->firstOrFail();

        $outsider = User::factory()->create()->assignRole('student');

        $this->actingAs($outsider)
            ->get(route('submissions.attachments.show', $attachment))
            ->assertForbidden();

        $response = $this->actingAs($this->student)
            ->get(route('submissions.attachments.show', $attachment));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('documento.pdf', $response->headers->get('content-disposition'));
    }

    private function makePdf(): string
    {
        return "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";
    }

    private function makeOoxml(string $extension, bool $withMacro = false): string
    {
        $contentTypesByExtension = [
            'docx' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
            'xlsx' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>',
            'pptx' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/></Types>',
        ];

        $mainPartByExtension = [
            'docx' => ['word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p/></w:body></w:document>'],
            'xlsx' => ['xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheets/></workbook>'],
            'pptx' => ['ppt/presentation.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"></p:presentation>'],
        ];

        $tmpPath = tempnam(sys_get_temp_dir(), 'ooxml_').'.'.$extension;

        $zip = new \ZipArchive();
        $zip->open($tmpPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $contentTypesByExtension[$extension]);
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>');
        [$partName, $partContent] = $mainPartByExtension[$extension];
        $zip->addFromString($partName, $partContent);

        if ($withMacro) {
            $zip->addFromString('word/vbaProject.bin', 'contenido-binario-simulado-de-macro');
        }

        $zip->close();

        $content = file_get_contents($tmpPath);
        unlink($tmpPath);

        return $content;
    }
}

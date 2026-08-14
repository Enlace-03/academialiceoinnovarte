<?php

namespace Tests\Feature\Assessment;

use App\Models\User;
use App\Modules\Project\Models\ExpectedEvidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * El paso más delicado del Hito 3b-3: la migración 2027_01_01_000400 no
 * solo crea submission_attachments, también COPIA los datos de las
 * columnas escalares viejas (file_disk/file_path/original_filename) antes
 * de eliminarlas -- entregas ya registradas por docentes en el Hito 2 no
 * deben perder su archivo.
 *
 * NUNCA copiar el bloque up()/down() de este test fuera de un test real:
 * fuera del proceso de PHPUnit, DB_DATABASE resuelve a 'liceo_innovarte'
 * (dev), no a 'liceo_innovarte_testing', y down() sí modifica esquema real
 * sin pedir confirmación (ver CLAUDE.md, regla de comandos destructivos).
 * El guard de abajo autoprotege incluso si esto se ejecuta fuera de
 * contexto -- verificado en vivo antes de escribir este test: phpunit.xml
 * fija DB_DATABASE=liceo_innovarte_testing como <env> explícito, y
 * .env.testing (que Laravel carga solo cuando APP_ENV=testing) coincide de
 * forma independiente.
 *
 * DDL (Schema::create/dropColumn) hace COMMIT implícito en MySQL/MariaDB --
 * este test no puede depender del rollback transaccional normal de
 * RefreshDatabase entre tests una vez que down()/up() corren, así que
 * limpia sus propios datos a mano en el finally, sin importar qué haga esa
 * transacción.
 */
class SubmissionAttachmentsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrating_up_preserves_existing_file_data_and_drops_old_columns(): void
    {
        abort_unless(
            config('database.connections.mysql.database') === 'liceo_innovarte_testing',
            500,
            'Este test solo puede correr contra la base de testing -- ver docblock de esta clase.'
        );

        $migration = require database_path('migrations/2027_01_01_000400_create_submission_attachments_table.php');

        $student = User::factory()->create();
        $evidence = ExpectedEvidence::factory()->create();
        $submissionId = null;

        try {
            // Vuelve al esquema viejo (columnas escalares, sin submission_attachments).
            $migration->down();

            $submissionId = DB::table('submissions')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'expected_evidence_id' => $evidence->id,
                'student_id' => $student->id,
                'status' => 'submitted',
                'submitted_at' => now(),
                'file_disk' => 'local',
                'file_path' => 'submissions/legacy-hito2.jpg',
                'original_filename' => 'entrega-hito2.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // El up() real, con su copia de datos -- esto es lo que se prueba.
            $migration->up();

            $this->assertDatabaseHas('submission_attachments', [
                'submission_id' => $submissionId,
                'type' => 'photo',
                'file_disk' => 'local',
                'file_path' => 'submissions/legacy-hito2.jpg',
                'original_filename' => 'entrega-hito2.jpg',
            ]);

            $this->assertFalse(Schema::hasColumn('submissions', 'file_path'));
            $this->assertFalse(Schema::hasColumn('submissions', 'file_disk'));
            $this->assertFalse(Schema::hasColumn('submissions', 'original_filename'));
        } finally {
            if ($submissionId !== null) {
                DB::table('submission_attachments')->where('submission_id', $submissionId)->delete();
                DB::table('submissions')->where('id', $submissionId)->delete();
            }

            $evidence->forceDelete();
            $student->delete();
        }
    }
}

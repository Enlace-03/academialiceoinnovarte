<?php

namespace Tests\Feature\Project;

use App\Modules\Project\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * La migración 2027_01_01_000410 hace backfill en el mismo up(): todo
 * proyecto ya existente (creado antes de que el concepto borrador/publicado
 * existiera) debe quedar 'published', no 'draft' -- no deben desaparecer del
 * portal de sus estudiantes por una migración de esquema. Mismo criterio de
 * invocación directa de up()/down() que SubmissionAttachmentsMigrationTest
 * (Hito 3b-3) -- ver ese archivo para el porqué (RefreshDatabase ya corrió
 * el esquema final antes de cada test, así que hay que revertir y volver a
 * aplicar esta migración puntual para ejercitar su up() real).
 */
class ProjectStatusMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_up_backfills_existing_projects_as_published(): void
    {
        abort_unless(
            config('database.connections.mysql.database') === 'liceo_innovarte_testing',
            500,
            'Este test solo puede correr contra la base de testing.'
        );

        $migration = require database_path('migrations/2027_01_01_000410_add_status_to_projects_table.php');

        // DDL (Schema::) hace COMMIT implícito en MySQL/MariaDB -- no se
        // puede depender del rollback transaccional normal de
        // RefreshDatabase una vez que down()/up() corren (mismo motivo que
        // SubmissionAttachmentsMigrationTest), así que limpia el proyecto y
        // sus 4 fases auto-generadas (ProjectObserver) a mano.
        $preexistingProject = Project::factory()->create();

        try {
            $migration->down();

            $this->assertFalse(Schema::hasColumn('projects', 'status'));

            $migration->up();

            $this->assertSame(
                'published',
                DB::table('projects')->where('id', $preexistingProject->id)->value('status')
            );
        } finally {
            $preexistingProject->phases()->forceDelete();
            $preexistingProject->forceDelete();
        }
    }
}

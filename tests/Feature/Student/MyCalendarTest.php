<?php

namespace Tests\Feature\Student;

use App\Livewire\Student\MyCalendar;
use App\Models\User;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\StudentPhaseSchedule;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class MyCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        /**
         * Fecha fija a mitad de mes (Laravel resetea esto automáticamente al
         * terminar cada test, ver travelTo()): evita que sumar/restar unos
         * días alrededor de "hoy" cruce sin querer a otro mes según el día
         * real en que corra la suite -- MyCalendar arranca siempre en el mes
         * de "hoy", así que ese cruce rompería estos tests solo cerca de fin
         * de mes.
         */
        $this->travelTo(Carbon::parse('2026-03-15 10:00:00'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('student.calendar'))->assertRedirect(route('login'));
    }

    public function test_teacher_cannot_access_the_student_calendar(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');

        $this->actingAs($teacher)
            ->get(route('student.calendar'))
            ->assertForbidden();
    }

    /**
     * Aislamiento (mismo rigor que el resto del portal): un estudiante no ve
     * marcas de entregas de otro estudiante, aunque caigan en el mismo mes.
     */
    public function test_student_does_not_see_another_students_pending_evidence(): void
    {
        $student = User::factory()->create()->assignRole('student');
        $otherStudent = User::factory()->create()->assignRole('student');

        $project = Project::factory()->create();
        $phase = $project->phases()->first();
        ExpectedEvidence::factory()->create(['phase_id' => $phase->id, 'description' => 'Entrega ajena']);
        StudentPhaseSchedule::factory()->create([
            'student_id' => $otherStudent->id,
            'phase_id' => $phase->id,
            'end_date' => '2026-03-20',
        ]);

        Livewire::actingAs($student)->test(MyCalendar::class)
            ->assertDontSee('Entrega ajena');
    }

    /**
     * Marca "vencido" (rojo) para un end_date ya pasado dentro del mes
     * visible que sigue sin resolver; "próximo" (ámbar) para uno futuro --
     * dos entregas del mismo estudiante, en días distintos del mismo mes.
     */
    public function test_marks_overdue_and_upcoming_days_with_different_colors(): void
    {
        $student = User::factory()->create()->assignRole('student');

        $project = Project::factory()->create();
        $phase = $project->phases()->first();
        ExpectedEvidence::factory()->create(['phase_id' => $phase->id, 'description' => 'Evidencia vencida']);
        StudentPhaseSchedule::factory()->create([
            'student_id' => $student->id,
            'phase_id' => $phase->id,
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-10',
        ]);

        $secondPhase = $project->phases()->skip(1)->first();
        ExpectedEvidence::factory()->create(['phase_id' => $secondPhase->id, 'description' => 'Evidencia próxima']);
        StudentPhaseSchedule::factory()->create([
            'student_id' => $student->id,
            'phase_id' => $secondPhase->id,
            'start_date' => '2026-03-15',
            'end_date' => '2026-03-20',
        ]);

        $pendingByDate = Livewire::actingAs($student)->test(MyCalendar::class)
            ->instance()
            ->pendingByDate();

        $this->assertTrue($pendingByDate->get('2026-03-10')->first()['due_date']->isPast());
        $this->assertFalse($pendingByDate->get('2026-03-20')->first()['due_date']->isPast());
    }

    /**
     * Clic en un día muestra la lista de evidencias de ese día, cada una
     * enlazando a su EvidenceShow real.
     */
    public function test_clicking_a_day_shows_its_evidence_linking_to_evidence_show(): void
    {
        $student = User::factory()->create()->assignRole('student');

        $project = Project::factory()->create();
        $phase = $project->phases()->first();
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $phase->id, 'description' => 'Maqueta final']);
        $dueDate = '2026-03-22';
        StudentPhaseSchedule::factory()->create([
            'student_id' => $student->id,
            'phase_id' => $phase->id,
            'end_date' => $dueDate,
        ]);

        $expectedUrl = route('student.evidence.show', ['project' => $project, 'evidence' => $evidence]);

        Livewire::actingAs($student)->test(MyCalendar::class)
            ->call('selectDate', $dueDate)
            ->assertSee('Maqueta final')
            ->assertSee($expectedUrl, false);
    }

    /**
     * Por defecto (sin día seleccionado) la columna lateral muestra TODAS
     * las entregas pendientes del mes visible, no solo las de un día --
     * pedido explícito de este hito (antes solo aparecían al hacer clic).
     */
    public function test_shows_all_month_entries_by_default_without_selecting_a_day(): void
    {
        $student = User::factory()->create()->assignRole('student');

        $project = Project::factory()->create();
        $phase = $project->phases()->first();
        ExpectedEvidence::factory()->create(['phase_id' => $phase->id, 'description' => 'Bitacora de campo']);
        StudentPhaseSchedule::factory()->create([
            'student_id' => $student->id,
            'phase_id' => $phase->id,
            'end_date' => '2026-03-20',
        ]);

        Livewire::actingAs($student)->test(MyCalendar::class)
            ->assertSee('Entregas de marzo')
            ->assertSee('Bitacora de campo');
    }

    /**
     * Si el mes visible no tiene ninguna entrega pendiente, la columna
     * lateral se oculta por completo -- sin mensaje "sin entregas", pedido
     * explícito de este hito.
     */
    public function test_hides_the_activities_panel_when_the_month_has_no_pending_entries(): void
    {
        $student = User::factory()->create()->assignRole('student');

        Livewire::actingAs($student)->test(MyCalendar::class)
            ->assertDontSee('Entregas de marzo');
    }

    /**
     * Con un día seleccionado, la columna lateral se filtra a ese día y
     * ofrece un link para volver a la vista de todo el mes (reutiliza
     * selectDate(), que ya deselecciona si se llama dos veces con la misma
     * fecha -- mismo mecanismo, no un método nuevo).
     */
    public function test_selecting_a_day_offers_a_link_back_to_the_full_month(): void
    {
        $student = User::factory()->create()->assignRole('student');

        $project = Project::factory()->create();
        $phase = $project->phases()->first();
        ExpectedEvidence::factory()->create(['phase_id' => $phase->id, 'description' => 'Maqueta final']);
        $dueDate = '2026-03-22';
        StudentPhaseSchedule::factory()->create([
            'student_id' => $student->id,
            'phase_id' => $phase->id,
            'end_date' => $dueDate,
        ]);

        $component = Livewire::actingAs($student)->test(MyCalendar::class)
            ->call('selectDate', $dueDate)
            ->assertSee('Ver todo el mes')
            ->assertDontSee('Entregas de marzo');

        $component->call('selectDate', $dueDate)
            ->assertSee('Entregas de marzo')
            ->assertDontSee('Ver todo el mes');
    }
}

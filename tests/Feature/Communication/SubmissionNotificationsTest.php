<?php

namespace Tests\Feature\Communication;

use App\Models\User;
use App\Modules\Assessment\Actions\RegisterSubmissionAction;
use App\Modules\Institution\Models\Institution;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use Database\Seeders\RolePermissionSeeder;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SubmissionNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Institution::factory()->create();
    }

    /**
     * Vía Filament\Notifications\Notification::sendToDatabase(), no una
     * clase Notification propia (ver docblock de NotifyTeacherOfNewSubmission)
     * -- su via() siempre es ['database'], nunca correo, garantizado por el
     * propio framework.
     */
    public function test_the_teacher_with_project_authority_is_notified_in_platform_only(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $phase = $project->phases()->first();
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $phase->id]);
        $student = User::factory()->create()->assignRole('student');

        Notification::fake();

        app(RegisterSubmissionAction::class)->execute($evidence, $student, []);

        Notification::assertSentTo($teacher, DatabaseNotification::class, function (DatabaseNotification $notification) {
            return $notification->data['format'] === 'filament';
        });
    }
}

<?php

namespace Tests\Feature\Tracking;

use App\Filament\Academic\Pages\TrackingWeightsPage;
use App\Models\User;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\Institution;
use App\Modules\Institution\Models\InstitutionSetting;
use App\Modules\Tracking\Actions\TrackingWeightsResolver;
use App\Modules\Tracking\Jobs\RecalculateAllProgressJob;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class TrackingWeightsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
        Institution::factory()->create();

        Filament::setCurrentPanel(Filament::getPanel('academic'));
    }

    public function test_rector_can_access_the_page(): void
    {
        $rector = User::factory()->create()->assignRole('rector');
        $this->actingAs($rector);

        $this->assertTrue(TrackingWeightsPage::canAccess());
    }

    public function test_teacher_cannot_access_the_page(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $this->actingAs($teacher);

        $this->assertFalse(TrackingWeightsPage::canAccess());
    }

    public function test_coordinator_cannot_access_the_page_only_rector_for_now(): void
    {
        $coordinator = User::factory()->create()->assignRole('coordinator');
        $this->actingAs($coordinator);

        $this->assertFalse(TrackingWeightsPage::canAccess());
    }

    public function test_saving_valid_weights_persists_global_and_each_cycle(): void
    {
        $cycle = Cycle::factory()->create();
        $rector = User::factory()->create()->assignRole('rector');
        $this->actingAs($rector);

        Livewire::test(TrackingWeightsPage::class)
            ->fillForm(['weights' => [
                'global' => ['evidencias' => 50, 'foro' => 30, 'chat' => 20],
                (string) $cycle->id => ['evidencias' => 70, 'foro' => 20, 'chat' => 10],
            ]])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(
            ['evidencias' => 50, 'foro' => 30, 'chat' => 20],
            json_decode(InstitutionSetting::get(TrackingWeightsResolver::GLOBAL_KEY), true),
        );
        $this->assertSame(
            ['evidencias' => 70, 'foro' => 20, 'chat' => 10],
            json_decode(InstitutionSetting::get(TrackingWeightsResolver::cycleKey($cycle->id)), true),
        );
    }

    public function test_a_row_that_does_not_sum_100_is_rejected_and_nothing_is_persisted(): void
    {
        $cycle = Cycle::factory()->create();
        $rector = User::factory()->create()->assignRole('rector');
        $this->actingAs($rector);

        Livewire::test(TrackingWeightsPage::class)
            ->fillForm(['weights' => [
                'global' => ['evidencias' => 60, 'foro' => 25, 'chat' => 15],
                (string) $cycle->id => ['evidencias' => 50, 'foro' => 30, 'chat' => 10], // suma 90
            ]])
            ->call('save')
            ->assertHasErrors(["data.weights.{$cycle->id}.evidencias"]);

        $this->assertNull(InstitutionSetting::get(TrackingWeightsResolver::GLOBAL_KEY));
        $this->assertNull(InstitutionSetting::get(TrackingWeightsResolver::cycleKey($cycle->id)));
    }

    public function test_recalculate_all_button_dispatches_the_mass_job(): void
    {
        Queue::fake();

        $rector = User::factory()->create()->assignRole('rector');
        $this->actingAs($rector);

        Livewire::test(TrackingWeightsPage::class)->call('recalculateAll');

        Queue::assertPushed(RecalculateAllProgressJob::class);
    }
}

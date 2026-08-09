<?php

declare(strict_types=1);

namespace App\Filament\Academic\Pages;

use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\InstitutionSetting;
use App\Modules\Tracking\Actions\TrackingWeightsResolver;
use App\Modules\Tracking\Jobs\RecalculateAllProgressJob;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Página nueva en /academia, no en /admin (decisión pedagógica confirmada
 * del Hito 4). Tabla editable de filas fijas (Global + los 4 ciclos), no un
 * Repeater -- las filas no se agregan ni se quitan, ya vienen dadas por
 * Cycle::all() + la fila global.
 *
 * Guardar escribe SIEMPRE las 5 filas como overrides explícitos (vía
 * InstitutionSetting::set()), no solo la fila que cambió -- simplifica el
 * guardado (una sola pasada, sin detectar "qué cambió") a costa de que un
 * único "Guardar" fija overrides explícitos también en ciclos que antes
 * heredaban del default. mount() precarga cada fila con lo que YA está
 * vigente para ella (su propio override, o lo que resolvería la cadena si
 * no tiene uno) para que esto no sea sorpresivo.
 */
class TrackingWeightsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | UnitEnum | null $navigationGroup = 'Seguimiento';

    protected static ?string $navigationLabel = 'Pesos de avance';

    protected static ?string $title = 'Configuración de pesos de avance';

    protected static ?string $slug = 'pesos-de-avance';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->hasPermissionTo('tracking.settings.manage') ?? false;
    }

    public function mount(): void
    {
        $weights = ['global' => $this->currentWeightsFor(TrackingWeightsResolver::GLOBAL_KEY, null)];

        foreach ($this->cycles() as $cycle) {
            $weights[(string) $cycle->id] = $this->currentWeightsFor(TrackingWeightsResolver::cycleKey($cycle->id), $cycle);
        }

        $this->form->fill(['weights' => $weights]);
    }

    public function form(Schema $schema): Schema
    {
        $components = [
            Section::make('Global (default)')
                ->description('Aplica a cualquier ciclo sin un ajuste propio abajo.')
                ->schema($this->weightFields('global'))
                ->columns(3),
        ];

        foreach ($this->cycles() as $cycle) {
            $components[] = Section::make($cycle->name)
                ->schema($this->weightFields((string) $cycle->id))
                ->columns(3);
        }

        return $schema->components($components)->statePath('data');
    }

    /**
     * @return array<TextInput>
     */
    protected function weightFields(string $rowKey): array
    {
        return [
            TextInput::make("weights.{$rowKey}.evidencias")->label('Evidencias')->numeric()->required()->suffix('%'),
            TextInput::make("weights.{$rowKey}.foro")->label('Foro')->numeric()->required()->suffix('%'),
            TextInput::make("weights.{$rowKey}.chat")->label('Chat')->numeric()->required()->suffix('%'),
        ];
    }

    public function save(): void
    {
        $weights = $this->form->getState()['weights'];

        $errors = [];

        foreach ($weights as $rowKey => $row) {
            $sum = (int) $row['evidencias'] + (int) $row['foro'] + (int) $row['chat'];

            if ($sum !== 100) {
                $label = $rowKey === 'global' ? 'Global' : (Cycle::find($rowKey)?->name ?? $rowKey);
                $errors["data.weights.{$rowKey}.evidencias"] = "Los tres valores de \"{$label}\" deben sumar 100 (suma actual: {$sum}).";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        InstitutionSetting::set(TrackingWeightsResolver::GLOBAL_KEY, $this->encodeRow($weights['global']));

        foreach ($this->cycles() as $cycle) {
            $row = $weights[(string) $cycle->id] ?? null;

            if ($row !== null) {
                InstitutionSetting::set(TrackingWeightsResolver::cycleKey($cycle->id), $this->encodeRow($row));
            }
        }

        Notification::make()
            ->title('Pesos guardados')
            ->body('Afecta solo el próximo recálculo natural de cada estudiante -- usa "Recalcular todo ahora" si quieres aplicarlo de inmediato.')
            ->success()
            ->send();
    }

    public function recalculateAll(): void
    {
        RecalculateAllProgressJob::dispatch();

        Notification::make()
            ->title('Recálculo masivo encolado')
            ->body('Correrá en segundo plano sobre todos los estudiantes y proyectos.')
            ->success()
            ->send();
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar')
                ->submit('save'),

            Action::make('recalculateAll')
                ->label('Recalcular todo ahora')
                ->color('gray')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalDescription('Encola un recálculo para todos los estudiantes y proyectos. No es automático ni necesario para que los pesos nuevos apliquen hacia adelante.')
                ->action('recalculateAll'),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make($this->getFormActions()),
                    ]),
            ]);
    }

    /**
     * @return Collection<int, Cycle>
     */
    protected function cycles(): Collection
    {
        return Cycle::query()->orderBy('order')->get();
    }

    /**
     * @return array{evidencias: int, foro: int, chat: int}
     */
    protected function currentWeightsFor(string $settingKey, ?Cycle $cycle): array
    {
        $raw = InstitutionSetting::get($settingKey);

        if ($raw !== null) {
            return json_decode($raw, true);
        }

        if ($cycle !== null) {
            return app(TrackingWeightsResolver::class)->forCycle($cycle);
        }

        return config('tracking.progress_weights');
    }

    /**
     * @param  array{evidencias: mixed, foro: mixed, chat: mixed}  $row
     */
    protected function encodeRow(array $row): string
    {
        return json_encode([
            'evidencias' => (int) $row['evidencias'],
            'foro' => (int) $row['foro'],
            'chat' => (int) $row['chat'],
        ]);
    }
}

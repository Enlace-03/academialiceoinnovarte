<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Modules\Institution\Models\InstitutionSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class InstitutionSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | UnitEnum | null $navigationGroup = 'Institución';

    protected static ?string $navigationLabel = 'Configuración institucional';

    protected static ?string $title = 'Configuración institucional';

    protected static ?string $slug = 'configuracion-institucional';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->hasPermissionTo('institution.settings.manage') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'current_academic_year' => InstitutionSetting::get(
                'current_academic_year',
                config('school.current_academic_year'),
            ),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('current_academic_year')
                    ->label('Año lectivo vigente')
                    ->numeric()
                    ->required(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        InstitutionSetting::set('current_academic_year', (string) $data['current_academic_year']);

        Notification::make()
            ->title('Configuración guardada')
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
}

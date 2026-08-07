<?php

namespace App\Filament\Academic\Resources\Observations;

use App\Filament\Academic\Resources\Observations\Pages\CreateObservation;
use App\Filament\Academic\Resources\Observations\Pages\EditObservation;
use App\Filament\Academic\Resources\Observations\Pages\ListObservations;
use App\Filament\Academic\Resources\Observations\Schemas\ObservationForm;
use App\Filament\Academic\Resources\Observations\Tables\ObservationsTable;
use App\Modules\Assessment\Models\Observation;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ObservationResource extends Resource
{
    protected static ?string $model = Observation::class;

    protected static ?string $recordTitleAttribute = 'content';

    protected static string | UnitEnum | null $navigationGroup = 'ABP';

    protected static ?string $navigationLabel = 'Observaciones';

    protected static ?string $modelLabel = 'Observación';

    protected static ?string $pluralModelLabel = 'Observaciones';

    public static function form(Schema $schema): Schema
    {
        return ObservationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ObservationsTable::configure($table);
    }

    /**
     * Un docente sin observations.write.all/view.all solo ve las suyas
     * (teacher_id), reforzando a nivel de listado lo que ObservationPolicy
     * ya exige a nivel de registro individual.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user !== null
            && ! $user->hasPermissionTo('observations.view.all')
            && ! $user->hasPermissionTo('observations.write.all')) {
            $query->where('teacher_id', $user->id);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListObservations::route('/'),
            'create' => CreateObservation::route('/create'),
            'edit' => EditObservation::route('/{record}/edit'),
        ];
    }
}

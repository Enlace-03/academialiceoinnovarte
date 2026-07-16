<?php

namespace App\Filament\Admin\Resources\Users\RelationManagers;

use App\Models\User;
use App\Modules\Identity\Actions\RecordDataTreatmentConsentAction;
use App\Modules\Identity\Models\ParentStudent;
use Closure;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;

class GuardiansRelationManager extends RelationManager
{
    protected static string $relationship = 'guardians';

    protected static ?string $title = 'Acudientes';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->hasRole('student');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(self::pivotFields());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->inverseRelationship('children')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),

                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable(),

                TextColumn::make('pivot.relationship')
                    ->label('Parentesco')
                    ->formatStateUsing(fn (?string $state): string => ParentStudent::RELATIONSHIP_OPTIONS[$state] ?? ($state ?? '—')),

                IconColumn::make('pivot.is_primary_contact')
                    ->label('Contacto principal')
                    ->boolean(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Adjuntar acudiente')
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(
                        fn (Builder $query) => $query->whereHas(
                            'roles',
                            fn (Builder $q) => $q->where('name', 'parent')
                        )
                    )
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->label('Acudiente')
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if ((string) $value === (string) $this->getOwnerRecord()->getKey()) {
                                    $fail('Un usuario no puede ser su propio acudiente.');
                                }
                            }),
                        ...self::pivotFields(),
                        Checkbox::make('data_treatment_consent')
                            ->label(sprintf(
                                'Confirmo que el acudiente autorizó el tratamiento de datos personales según la Política de Tratamiento de Datos vigente (versión %s).',
                                config('legal.data_treatment_policy_version'),
                            ))
                            ->required()
                            ->accepted()
                            ->validationMessages([
                                'accepted' => 'Debes confirmar el consentimiento de tratamiento de datos personales para adjuntar al acudiente.',
                            ]),
                        Section::make('Ver el texto completo de la política')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                Placeholder::make('data_treatment_policy_text')
                                    ->hiddenLabel()
                                    ->html()
                                    ->content(fn (): string => view('legal.data-consent.' . config('legal.data_treatment_policy_version'))->render()),
                            ]),
                    ])
                    ->using(function (array $data, User $record, BelongsToMany $relationship): void {
                        app(RecordDataTreatmentConsentAction::class)->execute(
                            $relationship,
                            $record,
                            Arr::only($data, $relationship->getPivotColumns()),
                            auth()->user(),
                        );
                    }),
            ])
            ->recordActions([
                EditAction::make()->schema(self::pivotFields()),
                DetachAction::make(),
            ]);
    }

    protected static function pivotFields(): array
    {
        return [
            Select::make('relationship')
                ->label('Parentesco')
                ->options(ParentStudent::RELATIONSHIP_OPTIONS)
                ->required(),

            Toggle::make('is_primary_contact')
                ->label('Contacto principal')
                ->inline(false),
        ];
    }
}

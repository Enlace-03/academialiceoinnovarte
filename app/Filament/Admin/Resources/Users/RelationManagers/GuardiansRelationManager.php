<?php

namespace App\Filament\Admin\Resources\Users\RelationManagers;

use App\Modules\Identity\Models\ParentStudent;
use Closure;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
                    ]),
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

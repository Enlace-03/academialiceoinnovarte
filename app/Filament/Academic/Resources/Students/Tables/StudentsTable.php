<?php

namespace App\Filament\Academic\Resources\Students\Tables;

use App\Models\User;
use App\Modules\Identity\Actions\BlockStudentPhotoUploadsAction;
use App\Modules\Identity\Actions\RemoveStudentPhotoAction;
use App\Modules\Identity\Actions\UnblockStudentPhotoUploadsAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['group.cycle', 'schoolGrade']))
            ->columns([
                ImageColumn::make('photo_path')
                    ->label('Foto')
                    ->getStateUsing(fn (User $record): string => $record->hasPhoto()
                        ? route('students.photo.show', $record)
                        : asset('images/generic-student-avatar.svg'))
                    ->circular(),

                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable(),

                TextColumn::make('grade_group')
                    ->label('Grado / Grupo')
                    ->getStateUsing(fn (User $record): string => $record->schoolGrade
                        ? "{$record->schoolGrade->name} - " . ($record->group?->name ?? '—')
                        : '—'),
            ])
            ->defaultSort('name')
            ->recordActions([
                self::removeStudentPhotoAction(),
                self::blockStudentPhotoUploadsAction(),
                self::unblockStudentPhotoUploadsAction(),
            ]);
    }

    /**
     * Las tres acciones de moderación de foto (students.photo.moderate,
     * decisión confirmada: solo coordinator/rector) -- gateadas dos veces
     * (visible() para la UI, Gate::authorize() dentro del action() como
     * defensa en profundidad contra una llamada Livewire manipulada), mismo
     * criterio que el resto de acciones gateadas por permiso puntual en este
     * proyecto (ver ChatMessagePolicy).
     */
    private static function removeStudentPhotoAction(): Action
    {
        return Action::make('removeStudentPhoto')
            ->label('Eliminar foto')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (User $record): bool => $record->hasPhoto()
                && Gate::allows('moderatePhoto', $record))
            ->action(function (User $record): void {
                Gate::authorize('moderatePhoto', $record);

                app(RemoveStudentPhotoAction::class)->execute(auth()->user(), $record);
            });
    }

    private static function blockStudentPhotoUploadsAction(): Action
    {
        return Action::make('blockStudentPhotoUploads')
            ->label('Bloquear subida de foto')
            ->icon('heroicon-o-lock-closed')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Se eliminará la foto actual (si hay) y el acudiente no podrá subir una nueva hasta que se desbloquee.')
            ->visible(fn (User $record): bool => ! $record->photo_upload_blocked
                && Gate::allows('moderatePhoto', $record))
            ->action(function (User $record): void {
                Gate::authorize('moderatePhoto', $record);

                app(BlockStudentPhotoUploadsAction::class)->execute(auth()->user(), $record);
            });
    }

    private static function unblockStudentPhotoUploadsAction(): Action
    {
        return Action::make('unblockStudentPhotoUploads')
            ->label('Desbloquear subida de foto')
            ->icon('heroicon-o-lock-open')
            ->color('gray')
            ->visible(fn (User $record): bool => $record->photo_upload_blocked
                && Gate::allows('moderatePhoto', $record))
            ->action(function (User $record): void {
                Gate::authorize('moderatePhoto', $record);

                app(UnblockStudentPhotoUploadsAction::class)->execute(auth()->user(), $record);
            });
    }
}

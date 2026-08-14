<?php

namespace App\Filament\Academic\Resources\Projects\Pages;

use App\Filament\Academic\Resources\Projects\ProjectResource;
use App\Modules\Project\Actions\SetProjectStatusAction;
use App\Modules\Project\Models\Project;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;

/**
 * Separación vista/edición: clic en un proyecto desde ListProjects aterriza
 * acá (ver ProjectsTable::recordUrl), no en EditProject directo. Las 7
 * pestañas (Relation Managers) se heredan gratis de
 * ProjectResource::getRelations() vía HasRelationManagers -- confirmado
 * contra el código real de Filament 4 antes de escribir esta página, no
 * asumido de memoria de v3 (ver plan del hito). EditAction::make() lleva al
 * formulario de edición existente sin configuración adicional, mismo
 * mecanismo que ya usa DeleteAction en EditProject.
 *
 * publish()/unpublish(): autorización vía ProjectPolicy::update() (own/all
 * ya existente) -- ni permiso nuevo ni rama nueva, mismo criterio que ya
 * gobierna quién puede editar el proyecto. requiresConfirmation() solo en
 * "Publicar" porque hace visible el proyecto de inmediato a todo un ciclo de
 * estudiantes; "Volver a borrador" es reversible con menor riesgo.
 */
class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),

            Action::make('publish')
                ->label('Publicar')
                ->icon('heroicon-o-eye')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('El proyecto quedará visible de inmediato para los estudiantes del ciclo correspondiente en /mis-proyectos.')
                ->visible(fn (Project $record): bool => $record->status === 'draft' && Gate::allows('update', $record))
                ->action(function (Project $record): void {
                    Gate::authorize('update', $record);

                    app(SetProjectStatusAction::class)->execute($record, 'published');
                }),

            Action::make('unpublish')
                ->label('Volver a borrador')
                ->icon('heroicon-o-eye-slash')
                ->color('gray')
                ->visible(fn (Project $record): bool => $record->status === 'published' && Gate::allows('update', $record))
                ->action(function (Project $record): void {
                    Gate::authorize('update', $record);

                    app(SetProjectStatusAction::class)->execute($record, 'draft');
                }),
        ];
    }
}

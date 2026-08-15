<?php

namespace App\Filament\Academic\Resources\Projects;

use App\Filament\Academic\Resources\Projects\Pages\CreateProject;
use App\Filament\Academic\Resources\Projects\Pages\EditProject;
use App\Filament\Academic\Resources\Projects\Pages\ListProjects;
use App\Filament\Academic\Resources\Projects\Pages\ViewProject;
use App\Filament\Academic\Resources\Projects\RelationManagers\ExpectedEvidencesRelationManager;
use App\Filament\Academic\Resources\Projects\RelationManagers\ForumPostsRelationManager;
use App\Filament\Academic\Resources\Projects\RelationManagers\ForumThreadsRelationManager;
use App\Filament\Academic\Resources\Projects\RelationManagers\PhasesRelationManager;
use App\Filament\Academic\Resources\Projects\RelationManagers\PrivateChatThreadsRelationManager;
use App\Filament\Academic\Resources\Projects\RelationManagers\ProjectTeamsRelationManager;
use App\Filament\Academic\Resources\Projects\RelationManagers\StudentPhaseSchedulesRelationManager;
use App\Filament\Academic\Resources\Projects\RelationManagers\StudentProgressRelationManager;
use App\Filament\Academic\Resources\Projects\Schemas\ProjectForm;
use App\Filament\Academic\Resources\Projects\Schemas\ProjectInfolist;
use App\Filament\Academic\Resources\Projects\Tables\ProjectsTable;
use App\Modules\Project\Models\Project;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string | UnitEnum | null $navigationGroup = 'ABP';

    protected static ?string $navigationLabel = 'Proyectos';

    protected static ?string $modelLabel = 'Proyecto';

    protected static ?string $pluralModelLabel = 'Proyectos';

    public static function form(Schema $schema): Schema
    {
        return ProjectForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProjectInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectsTable::configure($table);
    }

    /**
     * Un docente sin projects.view.all solo ve los proyectos que él creó
     * (created_by_user_id), reforzando a nivel de listado lo que
     * ProjectPolicy::view ya exige a nivel de registro individual.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user !== null && ! $user->hasPermissionTo('projects.view.all')) {
            $query->where('created_by_user_id', $user->id);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'view' => ViewProject::route('/{record}'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            PhasesRelationManager::class,
            ProjectTeamsRelationManager::class,
            StudentPhaseSchedulesRelationManager::class,
            ExpectedEvidencesRelationManager::class,
            ForumThreadsRelationManager::class,
            ForumPostsRelationManager::class,
            StudentProgressRelationManager::class,
            PrivateChatThreadsRelationManager::class,
        ];
    }
}

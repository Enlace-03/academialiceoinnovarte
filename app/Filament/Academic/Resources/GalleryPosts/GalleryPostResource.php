<?php

namespace App\Filament\Academic\Resources\GalleryPosts;

use App\Filament\Academic\Resources\GalleryPosts\Pages\CreateGalleryPost;
use App\Filament\Academic\Resources\GalleryPosts\Pages\EditGalleryPost;
use App\Filament\Academic\Resources\GalleryPosts\Pages\ListGalleryPosts;
use App\Filament\Academic\Resources\GalleryPosts\Schemas\GalleryPostForm;
use App\Filament\Academic\Resources\GalleryPosts\Tables\GalleryPostsTable;
use App\Modules\Community\Models\GalleryPost;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class GalleryPostResource extends Resource
{
    protected static ?string $model = GalleryPost::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string | UnitEnum | null $navigationGroup = 'Comunidad';

    protected static ?string $navigationLabel = 'Galería';

    protected static ?string $modelLabel = 'Publicación de galería';

    protected static ?string $pluralModelLabel = 'Galería';

    public static function form(Schema $schema): Schema
    {
        return GalleryPostForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GalleryPostsTable::configure($table);
    }

    /**
     * Un docente sin gallery.update.all solo ve las suyas
     * (created_by_user_id), reforzando a nivel de listado lo que
     * GalleryPostPolicy ya exige a nivel de registro individual -- mismo
     * patrón que ProjectResource/ObservationResource.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user !== null && ! $user->hasPermissionTo('gallery.update.all')) {
            $query->where('created_by_user_id', $user->id);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGalleryPosts::route('/'),
            'create' => CreateGalleryPost::route('/create'),
            'edit' => EditGalleryPost::route('/{record}/edit'),
        ];
    }
}

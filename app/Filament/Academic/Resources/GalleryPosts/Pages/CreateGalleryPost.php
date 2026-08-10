<?php

namespace App\Filament\Academic\Resources\GalleryPosts\Pages;

use App\Filament\Academic\Resources\GalleryPosts\GalleryPostResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGalleryPost extends CreateRecord
{
    protected static string $resource = GalleryPostResource::class;

    /**
     * created_by_user_id nunca viene del formulario (evita que se falsee
     * desde el cliente): siempre es quien está autenticado al crear -- mismo
     * patrón que CreateProject/CreateObservation.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }
}

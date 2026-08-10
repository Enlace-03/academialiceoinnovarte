<?php

namespace App\Filament\Academic\Resources\GalleryPosts\Pages;

use App\Filament\Academic\Resources\GalleryPosts\GalleryPostResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGalleryPosts extends ListRecords
{
    protected static string $resource = GalleryPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

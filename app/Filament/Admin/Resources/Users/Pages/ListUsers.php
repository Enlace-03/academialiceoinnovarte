<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        // "Personal" primero: es la pestaña más pequeña y la de consulta más
        // frecuente en el día a día de secretaría/rectoría. El primer elemento
        // del array queda como pestaña activa por defecto (ver HasTabs::getDefaultActiveTab()).
        return [
            'staff' => Tab::make('Personal')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                    'roles',
                    fn (Builder $q) => $q->whereIn('name', self::rolesInCategory('staff')),
                ))
                ->badge(fn (): int => User::whereHas(
                    'roles',
                    fn (Builder $q) => $q->whereIn('name', self::rolesInCategory('staff')),
                )->count()),

            'students' => Tab::make('Estudiantes')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                    'roles',
                    fn (Builder $q) => $q->where('name', 'student'),
                ))
                ->badge(fn (): int => User::whereHas(
                    'roles',
                    fn (Builder $q) => $q->where('name', 'student'),
                )->count()),

            'guardians' => Tab::make('Acudientes')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                    'roles',
                    fn (Builder $q) => $q->where('name', 'parent'),
                ))
                ->badge(fn (): int => User::whereHas(
                    'roles',
                    fn (Builder $q) => $q->where('name', 'parent'),
                )->count()),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected static function rolesInCategory(string $category): array
    {
        return array_keys(array_filter(
            config('permissions.role_categories', []),
            fn (string $roleCategory): bool => $roleCategory === $category,
        ));
    }
}

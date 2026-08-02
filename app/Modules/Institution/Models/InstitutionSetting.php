<?php

declare(strict_types=1);

namespace App\Modules\Institution\Models;

use Database\Factories\InstitutionSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

#[Fillable(['institution_id', 'key', 'value'])]
class InstitutionSetting extends Model
{
    use HasFactory;

    protected static function newFactory(): InstitutionSettingFactory
    {
        return InstitutionSettingFactory::new();
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $institutionId = Institution::query()->value('id');

        // Envuelto en un array: Cache::rememberForever() no cachea `null` (lo
        // trata como "sin cachear" y recalcula en cada llamada), y una fila
        // ausente es justamente el caso que más nos interesa no repegarle a
        // la base de datos en cada request.
        $cached = Cache::rememberForever(
            static::cacheKey($institutionId, $key),
            fn () => ['value' => static::query()
                ->where('institution_id', $institutionId)
                ->where('key', $key)
                ->value('value')],
        );

        return $cached['value'] ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        $institutionId = Institution::query()->value('id');

        static::query()->updateOrCreate(
            ['institution_id' => $institutionId, 'key' => $key],
            ['value' => $value],
        );

        Cache::forget(static::cacheKey($institutionId, $key));
    }

    protected static function cacheKey(?int $institutionId, string $key): string
    {
        return "institution_setting:{$institutionId}:{$key}";
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ParentStudent extends Pivot
{
    protected $table = 'parent_student';

    protected $casts = [
        'is_primary_contact' => 'boolean',
    ];

    /**
     * Catalog of valid values for the 'relationship' column, enforced at the
     * application layer (Filament form + this constant), not via a DB enum —
     * same spirit as config/permissions.php's catalog.
     */
    public const RELATIONSHIP_OPTIONS = [
        'madre' => 'Madre',
        'padre' => 'Padre',
        'acudiente' => 'Acudiente',
        'tutor' => 'Tutor',
        'otro' => 'Otro',
    ];
}

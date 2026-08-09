<?php

declare(strict_types=1);

namespace App\Modules\Communication\Support;

use App\Models\User;

/**
 * "Nombre completo (Grupo)" para desambiguar estudiantes con nombres
 * similares en salones que mezclan grados (segunda vuelta del Hito 5) --
 * usado en el contenido de toda notificación que referencia a un
 * estudiante puntual: entrega nueva, respuesta de foro, evaluación
 * recibida. Sin grupo asignado, cae a solo el nombre (fail-soft: nunca
 * bloquea el envío de la notificación por un dato faltante).
 *
 * Group::name YA es el nombre completo "Ciclo - Sección" (ej. "Conceptual -
 * A"), por diseño -- ver InstitutionSeeder.php ($groupName = "{$cycleName}
 * - {$section}") y su test (InstitutionSeederTest). NO volver a anteponer
 * el nombre del ciclo aquí: produce un duplicado visible ("Conceptual -
 * Conceptual - A"), un bug real que se detectó y corrigió durante la
 * verificación manual de este mismo cambio.
 */
trait FormatsStudentLabel
{
    protected function studentLabel(User $student): string
    {
        $group = $student->group;

        return $group !== null ? "{$student->name} ({$group->name})" : $student->name;
    }
}

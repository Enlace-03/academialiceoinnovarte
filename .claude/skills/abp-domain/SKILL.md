---
name: abp-domain
description: Cargar esta skill cuando se trabaje con entidades del dominio educativo del proyecto: proyectos ABP, fases, guías, evidencias, evaluaciones, progreso. Contiene el glosario de dominio y las relaciones entre entidades.
---

# Dominio ABP — Liceo Innovarte

## Glosario de entidades (usar estos nombres exactos en código)

| Entidad | Tabla | Descripción |
|---|---|---|
| **Proyecto ABP** | `projects` | Unidad central de trabajo. Dura 3 o 6 meses. Puede cubrir varias materias. Asignado a un grupo. |
| **Fase / Hito** | `phases` | Etapa ordenada de un proyecto. Un proyecto tiene 3-6 fases. |
| **Guía** | `guides` | Contenido base PRE-CREADO por el colegio. El docente NO crea guías desde cero — solo sube recursos complementarios. |
| **Recurso** | `resources` | Archivo, video o enlace que el docente agrega como complemento a una guía o fase. |
| **Evidencia esperada** | `expected_evidences` | Qué debe entregar el estudiante en una fase (archivo, texto, participación en foro). |
| **Entrega** | `submissions` | Lo que el estudiante sube como respuesta a una evidencia esperada. |
| **Evaluación** | `evaluations` | Acto de evaluar una entrega. Tiene resultados por criterio de rúbrica. |
| **Resultado de evaluación** | `evaluation_results` | Nivel asignado a cada criterio de la rúbrica para una evaluación. |
| **Rúbrica** | `rubrics` | Instrumento de evaluación con múltiples criterios. |
| **Criterio de rúbrica** | `rubric_criteria` | Un aspecto a evaluar (ej. "Comprensión del tema"). Tiene descripción por nivel. |
| **Observación** | `observations` | Nota cualitativa libre del docente sobre un estudiante. Visible a padres. |
| **Barra de avance** | `student_progress.progress_pct` | Porcentaje 0-100 calculado por fórmula ponderada. NO es una nota. |
| **Métricas** | `student_metrics` | Indicadores precalculados por job nocturno. Fuente de los dashboards. |
| **Snapshot** | `performance_snapshots` | Foto semanal de las métricas. Fuente de gráficas de evolución. |
| **Evento de aprendizaje** | `learning_events` | Log inmutable de todo lo que ocurre. Fuente de verdad para analítica. |
| **Alerta de riesgo** | `risk_alerts` | Generada por reglas cuando un estudiante muestra patrones de rezago. |
| **Predicción** | `predictions` | Estimación de probabilidad de completar el proyecto o de riesgo de no terminar. |

## Jerarquía del modelo educativo

```
Institución
└── Grado (school_grade: 1° a 9°)
    └── Grupo (group: 5°-A, 5°-B)
        └── Proyecto ABP (3 o 6 meses, cubre N materias)
            └── Fase / Hito (ordenada, con fecha sugerida)
                ├── Guías (contenido base, pre-creadas)
                │   └── Recursos (archivos del docente)
                └── Evidencias esperadas
                    └── Entregas del estudiante
                        └── Evaluación con rúbrica
```

## Fórmula de la barra de avance

```
progress_pct = (
    guías_completadas / guías_totales        × 0.50 +
    participaciones_foro / esperadas_foro    × 0.20 +
    participaciones_chat / esperadas_chat    × 0.10 +
    evaluaciones_alcanzó_o_superó / total    × 0.20
) × 100
```

Pesos configurables en `config/tracking.php`. Calculado por `RecalculateProgressAction`.
El resultado se guarda en `student_progress.progress_pct`.

## Los 4 niveles de evaluación

```php
enum RubricLevel: string {
    case NotAchieved       = 'not_achieved';        // No alcanzó — rojo
    case PartiallyAchieved = 'partially_achieved';  // Alcanzó medianamente — amarillo
    case Achieved          = 'achieved';            // Alcanzó — verde
    case Exceeded          = 'exceeded';            // Superó — azul
}
```

Valor interno para cálculos: 1, 2, 3, 4. NUNCA mostrar este número al usuario.
En Filament: `TextColumn::badge()` con colores danger/warning/success/info.
En Livewire/Blade: componente `<x-rubric-badge :level="$level" />`.

## Los 4 avatares

| Clave | Nombre | Aparece para | Función |
|---|---|---|---|
| `rectora_isabel` | Rectora Isabel | Todos (onboarding) | Bienvenida, explica contraseñas |
| `docente_guia` | Docente guía | Estudiantes + padres | Navegación, competencias, evaluación |
| `mentor_nino` | Estudiante mentor (niño) | Estudiantes primaria (1°-5°) | Acompañamiento, motivación |
| `mentora_mujer` | Estudiante mentora (mujer) | Estudiantes secundaria (6°-9°) | Evaluaciones, evidencias, motivación |

Mensajes en tabla `avatar_messages`. Sin IA generativa en MVP — scripts pre-redactados.

## Reglas de negocio críticas

- Un docente solo puede crear/editar proyectos de sus grupos asignados (`teacher_assignments`).
- El rector puede ver/editar cualquier proyecto de la institución.
- Un estudiante solo ve sus propios datos (filtrar siempre por `auth()->id()`).
- Un padre solo ve los hijos vinculados en `parent_student`.
- Las guías son de solo lectura para docentes — no se editan, solo se agregan recursos.
- Una entrega solo puede evaluarse una vez (unique en `evaluations.submission_id`).
- Las observaciones son visibles a padres por defecto (`visible_to_parents = true`).
- La barra de avance NO es una calificación — nunca llamarla "nota" en la UI.

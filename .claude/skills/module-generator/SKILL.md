---
name: module-generator
description: Use this skill whenever creating a new module under app/Modules/ or adding a new feature (Action, Model, Event, Policy) to an existing module. Also covers when to create a Filament Resource vs a Livewire Component for the UI layer.
---

# Generador de módulos — Liceo Innovarte

## Estructura de un módulo (dominio puro, sin UI)

```
app/Modules/{Nombre}/
├── Models/
├── Actions/
├── Policies/
├── Events/
├── Listeners/
├── Jobs/
├── DTOs/
└── Enums/            # si aplica
```

Los módulos NO contienen controladores, vistas ni componentes de UI.
La UI vive en `app/Filament/` (admin/académico) o en `app/Livewire/` (estudiante/padre).

## Decisión: ¿Filament Resource o Livewire Component?

| ¿Quién lo usa? | ¿Qué tipo de pantalla? | → Usar |
|---|---|---|
| Admin o rectora | CRUD completo | Filament Resource en `Filament/Admin/` |
| Profesor | CRUD, evaluar, observar | Filament Resource en `Filament/Academic/` |
| Profesor | Dashboard con KPIs | Filament Widget en `Filament/Academic/` |
| Estudiante | Ver contenido, entregar, foro | Livewire Component en `Livewire/Student/` |
| Padre | Ver avance del hijo | Livewire Component en `Livewire/Parent/` |
| Ambos (estud/padre) | Avatar dock | Livewire Component en `Livewire/Shared/` |

## Checklist al crear una feature nueva

1. **Migración** (si aplica) → skill `migration-conventions`.
2. **Model** en `Modules/{X}/Models/`: relaciones, casts, scopes. Trait `HasUuid`.
3. **Policy** en `Modules/{X}/Policies/`: registrar en `AuthServiceProvider`.
   Filament la respeta automáticamente.
4. **Action** en `Modules/{X}/Actions/`: método `execute()`, strict types,
   evento de dominio al final.
5. **Event + Listeners** en módulos de Tracking/Prediction/Communication.
6. **UI**:
   - Si es para admin/profe/rectora → Filament Resource (skill `filament-conventions`).
   - Si es para estudiante/padre → Livewire Component + vista Blade.
7. **Feature test** en Pest: caso feliz + autorización.

## Reglas que rompen el build

- Lógica de negocio en Filament Resources o Livewire Components → mover a Action.
- Escritura directa en `learning_events` → usar evento de dominio.
- Mostrar valor numérico de rúbrica (1-4) → usar badge/componente.
- Query de dashboard a `learning_events` → leer de `student_metrics`.
- Pantalla de estudiante/padre usando Filament → mover a Livewire+Blade.

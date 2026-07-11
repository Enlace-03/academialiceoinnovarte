---
name: git-workflow
description: Cargar esta skill cuando se trabaje con Git, ramas, commits, o el proceso de deploy a cPanel. Cubre el flujo de dos ramas y las restricciones del hosting sin SSH.
---

# Git Workflow — Liceo Innovarte

## Dos ramas: main y deploy

| Rama | Propósito | Contiene vendor/ y public/build/ |
|---|---|---|
| `main` | Desarrollo diario | ❌ No (en .gitignore) |
| `deploy` | Lo que cPanel descarga | ✅ Sí (comiteados) |

**Regla:** siempre desarrollar en `main`. La rama `deploy` solo se actualiza para subir al servidor.

## Flujo diario de desarrollo

```bash
# 1. Siempre en main
git checkout main

# 2. Trabajar normalmente
# ... código, tests, etc ...

# 3. Commit en main
git add .
git commit -m "feat: descripción del cambio"
git push origin main
```

## Convención de commits (Conventional Commits)

```
feat: nueva funcionalidad
fix: corrección de bug
chore: tareas de mantenimiento (deps, config)
refactor: refactoring sin cambio de comportamiento
test: agregar o corregir tests
docs: documentación
style: formato, espacios (sin cambio de lógica)
```

Ejemplos reales del proyecto:
```
feat: crear ProjectResource en panel académico
feat: agregar EvaluateSubmissionAction con test
fix: corregir cálculo de barra de avance en fases vacías
chore: actualizar dependencias de Filament
refactor: extraer lógica de riesgo a RiskEvaluationService
```

## Flujo de deploy a cPanel

```bash
# 1. Asegurarse de que main está limpio y pusheado
git checkout main
git status  # debe estar limpio

# 2. Cambiar a deploy y mergear
git checkout deploy
git merge main

# 3. Compilar todo localmente
npm run build
composer install --no-dev --optimize-autoloader
php artisan filament:assets

# 4. Comitear artefactos compilados
git add -f vendor public/build public/css/filament public/js/filament
git commit -m "deploy: $(date +'%Y-%m-%d %H:%M')"

# 5. Push a GitHub
git push origin deploy

# 6. En cPanel → Git Version Control → Update from Remote
# Si hay migraciones nuevas: crear archivo storage/app/.migrate-pending

# 7. Volver a main
git checkout main
```

## Qué va en .gitignore (main)

```gitignore
vendor/
public/build/
public/css/filament/
public/js/filament/
.env
.env.production
storage/app/private/
storage/logs/
node_modules/
```

## Qué NO va en .gitignore (deploy)

En la rama `deploy`, los artefactos compilados se fuerzan con `git add -f`:
- `vendor/` — dependencias de Composer
- `public/build/` — assets compilados por Vite
- `public/css/filament/` — assets de Filament
- `public/js/filament/` — assets de Filament

## Manejo de migraciones sin SSH

Cuando hay migraciones nuevas en un deploy:

**Opción A** (si cPanel tiene Terminal):
```bash
cd /home/USUARIO/liceo-innovarte
php artisan migrate --force
```

**Opción B** (sin Terminal — archivo bandera):
```bash
# En local, crear el archivo bandera
# El scheduler lo detecta y corre las migraciones
touch storage/app/.migrate-pending
git add -f storage/app/.migrate-pending
git commit -m "deploy: trigger migration"
git push origin deploy
```

El scheduler en producción tiene este comando:
```php
Schedule::command('migrate --force')
    ->when(fn () => Storage::exists('.migrate-pending'))
    ->after(fn () => Storage::delete('.migrate-pending'));
```

## Comandos Git frecuentes en este proyecto

```bash
git log --oneline -10          # últimos 10 commits
git diff main deploy           # qué diferencia hay entre ramas
git stash                      # guardar cambios sin commitear
git stash pop                  # recuperar cambios guardados
git checkout main -- archivo   # recuperar un archivo de main
```

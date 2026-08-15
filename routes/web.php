<?php

use App\Livewire\Parent\ChildEvidenceShow;
use App\Livewire\Parent\ChildFieldProjects;
use App\Livewire\Parent\ChildProjectShow;
use App\Livewire\Parent\ChildThinkingFields;
use App\Livewire\Shared\Gallery;
use App\Livewire\Shared\Login;
use App\Livewire\Shared\PortalHome;
use App\Livewire\Student\EvidenceShow;
use App\Livewire\Student\ForumThreadShow;
use App\Livewire\Student\GroupChat;
use App\Livewire\Student\MyProjects;
use App\Livewire\Student\ProjectShow;
use App\Models\User;
use App\Modules\Assessment\Models\SubmissionAttachment;
use App\Modules\Community\Models\ChatMessage;
use App\Modules\Community\Models\ForumPostPhoto;
use App\Modules\Community\Models\GalleryPhoto;
use App\Modules\Identity\Actions\EndStudentSessionAction;
use App\Modules\Identity\Actions\GrantStudentSessionAction;
use App\Modules\Institution\Models\Group;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Panel fuera de Filament (Hito 3b-0): login mínimo compartido, guard web
// estándar de Laravel. No colisiona con /admin ni /academia — Filament
// gestiona sus propias rutas/middleware de panel, pero comparte el mismo
// guard 'web', así que la sesión es una sola en todo el sitio; cada panel
// decide qué mostrar según canAccessPanel()/Policies, no según un guard
// distinto.
Route::get('/login', Login::class)->middleware('guest')->name('login');

/**
 * Mismo botón, mismo endpoint, para cierre normal (cuenta propia) y para
 * "Terminar clase" (sesión entregada, Hito 3b-2) — la vista decide el texto
 * según session('active_grant_id'); esta ruta solo necesita, además del
 * logout de siempre, cerrar la entrega activa si la hay (ended_at = now()),
 * ANTES de invalidar la sesión (session('active_grant_id') deja de existir
 * después de invalidate()).
 */
Route::post('/logout', function () {
    $grantId = session('active_grant_id');

    if ($grantId !== null) {
        app(EndStudentSessionAction::class)->execute($grantId);
    }

    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

/**
 * "Entregar sesión" (Hito 3b-2): a propósito una página plana con un
 * <form> HTML normal, NADA de Livewire/Filament en el paso que cambia de
 * identidad. Probado en Chrome de forma reproducible: al hacer el
 * auth-switch (Auth::loginUsingId) desde dentro de un action() de Filament
 * -- incluso separándolo en dos pasos con successRedirectUrl() -- el
 * navegador queda "mid-navigation" varios segundos y termina de vuelta en
 * /academia con 403, en vez de aterrizar en /mis-proyectos. El servidor SÍ
 * ejecuta todo correctamente (la entrega queda registrada, el auth sí
 * cambia) — el problema es puramente el manejo de la redirección en el
 * cliente cuando cruza de la SPA de Filament hacia una app Livewire
 * completamente distinta a mitad de cambio de identidad. Un <form
 * method="POST"> normal, con una respuesta redirect() de Laravel corriente,
 * no tiene ese problema: es exactamente lo mismo que ya hace /logout.
 */
Route::get('/academia/grupos/{group}/entregar-sesion', function (Group $group) {
    Gate::authorize('create', [ChatMessage::class, $group]);

    $students = User::role('student')->where('group_id', $group->id)->orderBy('name')->get(['id', 'name']);

    return view('academic.grant-session', ['group' => $group, 'students' => $students]);
})->middleware('auth')->name('academic.group-sessions.create');

Route::post('/academia/grupos/{group}/entregar-sesion', function (Group $group) {
    Gate::authorize('create', [ChatMessage::class, $group]);

    $data = request()->validate(['student_id' => ['required', 'integer', 'exists:users,id']]);
    $student = User::findOrFail($data['student_id']);

    abort_unless($student->hasRole('student') && $student->group_id === $group->id, 422);

    app(GrantStudentSessionAction::class)->execute(
        auth()->user(), $student, $group, request()->ip(), request()->userAgent()
    );

    return redirect()->route('student.projects.index');
})->middleware('auth')->name('academic.group-sessions.store');

Route::get('/', PortalHome::class)->middleware(['auth', 'expire-delivered-session'])->name('portal.home');

/**
 * Galería (estudiante y acudiente, sin distinción de rol a nivel de ruta --
 * mismo criterio que portal.home): la audiencia real se resuelve dentro del
 * componente vía GalleryPostPolicy. Las dos rutas de abajo sirven el
 * archivo físico de cada foto individual -- disco 'local' (privado) a
 * propósito, nunca 'public': una publicación de un ciclo no debe ser
 * accesible por URL directa desde otro ciclo aunque alguien la adivine, el
 * mismo aislamiento que ya exige canAccessProject() en el resto de la app.
 * Gate::authorize('view', $photo->post) es la misma Policy que gobierna si
 * el post aparece en el listado -- un solo punto de verdad.
 */
Route::get('/galeria', Gallery::class)->middleware(['auth', 'expire-delivered-session'])->name('portal.gallery');

Route::get('/galeria/fotos/{photo:uuid}', function (GalleryPhoto $photo) {
    Gate::authorize('view', $photo->post);

    return Storage::disk($photo->file_disk)->response($photo->file_path);
})->middleware('auth')->name('gallery.photos.show');

Route::get('/foro/fotos/{photo:uuid}', function (ForumPostPhoto $photo) {
    Gate::authorize('view', $photo->post);

    return Storage::disk($photo->file_disk)->response($photo->file_path);
})->middleware('auth')->name('forum.photos.show');

/**
 * Adjuntos de entrega (Hito 3b-3), mismo criterio que gallery.photos.show /
 * forum.photos.show: disco 'local' (privado), autorización delegada 100% a
 * SubmissionPolicy::view() -- un solo punto de verdad, el mismo que decide
 * si la entrega aparece en la pantalla del estudiante o del docente. Solo
 * sirve adjuntos type=photo con archivo; uno type=link no tiene file_path,
 * nunca se navega a esta ruta para esos (se renderiza directo con la url
 * guardada).
 */
Route::get('/entregas/adjuntos/{attachment:uuid}', function (SubmissionAttachment $attachment) {
    Gate::authorize('view', $attachment->submission);

    return Storage::disk($attachment->file_disk)->response($attachment->file_path);
})->middleware('auth')->name('submissions.attachments.show');

/**
 * Foto de perfil de estudiante -- mismo criterio de disco privado que las
 * tres rutas de arriba. Autorización vía UserPolicy::viewPhoto(): personal o
 * alguno de los acudientes del propio estudiante, nunca por adivinar la URL
 * (uuid del estudiante, regla absoluta #5).
 */
Route::get('/estudiantes/{student:uuid}/foto', function (User $student) {
    Gate::authorize('viewPhoto', $student);

    abort_unless($student->hasPhoto(), 404);

    return Storage::disk($student->photo_disk)->response($student->photo_path);
})->middleware('auth')->name('students.photo.show');

/**
 * Portal de estudiante (Hito 3b-1). role:student, no parent — el padre se
 * queda en el placeholder de PortalHome hasta que se diseñe su propio
 * dashboard (fuera de alcance de este hito). Los modelos se resuelven por
 * la columna 'uuid' (regla absoluta #5: nunca IDs autoincrementales en URLs
 * de estudiantes), no por la 'id' interna.
 *
 * expire-delivered-session (Hito 3b-2): no-op para un login normal de
 * estudiante/padre -- solo actúa si session('active_grant_id') existe.
 */
Route::middleware(['auth', 'expire-delivered-session', 'role:student'])->group(function () {
    Route::get('/mis-proyectos', MyProjects::class)->name('student.projects.index');
    Route::get('/mis-proyectos/{project:uuid}', ProjectShow::class)->name('student.projects.show');
    /**
     * withoutScopedBindings(): dos parámetros con :campo explícito en la
     * misma ruta activan el scoping automático de Laravel (intenta resolver
     * 'thread' como hijo de 'project' vía una relación adivinada por
     * convención de nombre — 'threads()', que no existe; el modelo real es
     * forumThreads()). Se resuelve 'thread' de forma independiente por su
     * propio uuid; el chequeo de que pertenezca al {project} de la URL se
     * hace a mano en ForumThreadShow::mount() (404 si no coincide).
     */
    Route::get('/mis-proyectos/{project:uuid}/foro/{thread:uuid}', ForumThreadShow::class)
        ->name('student.forum.show')
        ->withoutScopedBindings();
    /**
     * withoutScopedBindings() (Hito 3b-3), mismo motivo que la ruta de foro:
     * ExpectedEvidence no cuelga de Project por una relación de nombre
     * adivinable ('evidences()' no existe), y ni siquiera cuelga
     * directamente -- va por Phase. Se resuelve 'evidence' por su propio
     * uuid; EvidenceShow::mount() verifica a mano que
     * $evidence->phase->project->id === $project->id (404 si no coincide).
     */
    Route::get('/mis-proyectos/{project:uuid}/evidencias/{evidence:uuid}', EvidenceShow::class)
        ->name('student.evidence.show')
        ->withoutScopedBindings();
    Route::get('/mi-grupo/chat', GroupChat::class)->name('student.chat');
});

/**
 * Drill-down del acudiente (campo de pensamiento → proyectos → detalle de
 * proyecto/evidencia), en modo estrictamente de solo lectura. role:parent,
 * no student -- simétrico al bloque de arriba.
 *
 * {child:uuid} resuelto por su propio uuid (regla absoluta #5, igual que
 * {project:uuid}/{evidence:uuid} en el bloque de estudiante) -- nunca por
 * el id autoincremental. isGuardianOf($child) se re-verifica en el
 * mount() de CADA componente, no solo acá ni solo en la Policy más
 * profunda -- mismo criterio de defensa en profundidad que
 * EvidenceShow::mount() ya aplica con la pertenencia evidencia↔proyecto:
 * un acudiente de OTRA familia con el uuid correcto de un hijo ajeno no
 * debe pasar ningún nivel, no solo el primero.
 *
 * withoutScopedBindings() en las tres rutas con más de un parámetro de
 * modelo ({child}+{field}, {child}+{project}, {child}+{project}+{evidence}):
 * User no tiene una relación projects()/thinkingFields() adivinable por
 * convención de nombre desde la que Laravel pudiera intentar el scoping
 * automático -- cada mount() resuelve y verifica la relación real a mano,
 * mismo motivo que ya exige withoutScopedBindings() en las rutas de
 * estudiante de arriba.
 */
Route::middleware(['auth', 'expire-delivered-session', 'role:parent'])->group(function () {
    Route::get('/hijos/{child:uuid}/campos', ChildThinkingFields::class)
        ->name('parent.child.fields');

    Route::get('/hijos/{child:uuid}/campos/{field:slug}/proyectos', ChildFieldProjects::class)
        ->name('parent.child.field.projects')
        ->withoutScopedBindings();

    Route::get('/hijos/{child:uuid}/proyectos/{project:uuid}', ChildProjectShow::class)
        ->name('parent.child.project.show')
        ->withoutScopedBindings();

    Route::get('/hijos/{child:uuid}/proyectos/{project:uuid}/evidencias/{evidence:uuid}', ChildEvidenceShow::class)
        ->name('parent.child.evidence.show')
        ->withoutScopedBindings();
});

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * archived_at nulo = visible en la campanita del portal (mismo criterio
     * que StudentSessionGrant.ended_at / DataTreatmentConsent: nunca se
     * borra el registro, solo se oculta de la vista normal). Solo la
     * consume NotificationBell del portal -- el panel nativo de Filament en
     * /academia ignora esta columna por completo (su query no la conoce),
     * así que agregarla es seguro para ambos consumidores de la misma tabla
     * física `notifications`.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('read_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};

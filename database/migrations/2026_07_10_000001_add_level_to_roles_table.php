<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // Hierarchy level of the role. Higher number = more authority.
            // This is the basis of the "delegation ceiling": a user can only
            // assign roles/permissions within their own maximum level.
            $table->unsignedInteger('level')->default(0)->after('guard_name');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('level');
        });
    }
};

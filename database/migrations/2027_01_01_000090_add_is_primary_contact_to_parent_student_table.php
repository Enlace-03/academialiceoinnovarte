<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parent_student', function (Blueprint $table) {
            $table->boolean('is_primary_contact')->default(false)->after('relationship');
        });
    }

    public function down(): void
    {
        Schema::table('parent_student', function (Blueprint $table) {
            $table->dropColumn('is_primary_contact');
        });
    }
};

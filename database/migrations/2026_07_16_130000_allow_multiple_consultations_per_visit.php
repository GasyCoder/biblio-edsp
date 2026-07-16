<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultation_sessions', function (Blueprint $table) {
            $table->dropForeign(['visit_id']);
            $table->dropUnique(['visit_id']);
            $table->foreign('visit_id')->references('id')->on('visits')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('consultation_sessions', function (Blueprint $table) {
            $table->dropForeign(['visit_id']);
            $table->unique('visit_id');
            $table->foreign('visit_id')->references('id')->on('visits')->restrictOnDelete();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->string('checked_in_role', 60)->nullable()->after('checked_in_by');
            $table->string('checked_out_role', 60)->nullable()->after('checked_out_by');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn(['checked_in_role', 'checked_out_role']);
        });
    }
};

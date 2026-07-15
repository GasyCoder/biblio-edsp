<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('type', 30)->default('shelf')->after('code');
            $table->string('number', 50)->nullable()->after('type');
            $table->index(['type', 'number']);
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropIndex(['type', 'number']);
            $table->dropColumn(['type', 'number']);
        });
    }
};

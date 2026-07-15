<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_rows', fn (Blueprint $table) => $table->foreignId('book_id')->nullable()->after('student_id')->constrained()->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('import_rows', fn (Blueprint $table) => $table->dropConstrainedForeignId('book_id'));
    }
};

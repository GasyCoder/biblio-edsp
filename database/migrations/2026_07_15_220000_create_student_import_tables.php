<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('repetition_code', 1)->default('N')->after('gender');
            $table->string('nationality')->nullable()->after('birth_date');
            $table->text('address')->nullable()->after('phone');
        });

        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->index();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('sheet_name')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('committed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('original_data');
            $table->json('normalized_data')->nullable();
            $table->json('errors')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['import_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('imports');
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['repetition_code', 'nationality', 'address']);
        });
    }
};

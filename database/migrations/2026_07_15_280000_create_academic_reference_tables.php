<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_levels', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('academic_mentions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('academic_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mention_id')->constrained('academic_mentions')->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('academic_level_program', function (Blueprint $table) {
            $table->foreignId('level_id')->constrained('academic_levels')->cascadeOnDelete();
            $table->foreignId('program_id')->constrained('academic_programs')->cascadeOnDelete();
            $table->primary(['level_id', 'program_id']);
        });
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('level_id')->nullable()->after('nationality')->constrained('academic_levels')->nullOnDelete();
            $table->foreignId('mention_id')->nullable()->after('level_id')->constrained('academic_mentions')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->after('mention_id')->constrained('academic_programs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_id');
            $table->dropConstrainedForeignId('mention_id');
            $table->dropConstrainedForeignId('level_id');
        });
        Schema::dropIfExists('academic_level_program');
        Schema::dropIfExists('academic_programs');
        Schema::dropIfExists('academic_mentions');
        Schema::dropIfExists('academic_levels');
    }
};

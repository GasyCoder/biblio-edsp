<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->string('visit_number', 40)->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->timestamp('checked_in_at');
            $table->timestamp('checked_out_at')->nullable();
            $table->foreignId('checked_in_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('open_student_id')->nullable()->virtualAs('if(`checked_out_at` is null, `student_id`, null)');
            $table->timestamps();
            $table->unique('open_student_id');
            $table->index(['student_id', 'checked_in_at']);
            $table->index('checked_out_at');
        });

        Schema::create('consultation_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_number', 40)->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('open_student_id')->nullable()->virtualAs('if(`closed_at` is null, `student_id`, null)');
            $table->timestamps();
            $table->unique('open_student_id');
            $table->index(['student_id', 'opened_at']);
        });

        Schema::create('consultation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('copy_id')->constrained()->restrictOnDelete();
            $table->timestamp('scanned_at');
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('scanned_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('returned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('active_copy_id')->nullable()->virtualAs('if(`returned_at` is null, `copy_id`, null)');
            $table->timestamps();
            $table->unique(['consultation_session_id', 'copy_id']);
            $table->unique('active_copy_id');
            $table->index(['consultation_session_id', 'returned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_items');
        Schema::dropIfExists('consultation_sessions');
        Schema::dropIfExists('visits');
    }
};

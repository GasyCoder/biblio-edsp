<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_number', 40)->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('due_at');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['student_id', 'closed_at']);
        });

        Schema::create('loan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('copy_id')->constrained()->restrictOnDelete();
            $table->timestamp('loaned_at');
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('loaned_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unsignedBigInteger('active_copy_id')->nullable()->virtualAs('if(`returned_at` is null, `copy_id`, null)');
            $table->unique('active_copy_id');
            $table->index(['loan_id', 'returned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_items');
        Schema::dropIfExists('loans');
    }
};

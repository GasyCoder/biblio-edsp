<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('registration_number', 32)->unique();
            $table->string('academic_number', 64)->nullable()->unique();
            $table->string('last_name');
            $table->string('first_name');
            $table->string('gender', 20)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('level', 100)->nullable();
            $table->string('program', 150)->nullable();
            $table->string('academic_year', 20)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status', 30)->default('active');
            $table->text('restriction_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'last_name', 'first_name']);
        });

        Schema::create('student_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('card_number', 80)->unique();
            $table->string('type', 30);
            $table->string('symbology', 20)->default('qr');
            $table->string('status', 30)->default('active');
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('replaced_by_id')->nullable()->constrained('student_cards')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('active_student_id')->nullable()->virtualAs("if(`status` = 'active', `student_id`, null)");
            $table->timestamps();
            $table->unique('active_student_id');
            $table->index(['student_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->string('display_name')->index();
            $table->string('last_name')->nullable();
            $table->string('first_name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->string('publisher')->nullable();
            $table->text('summary')->nullable();
            $table->string('isbn', 32)->nullable()->index();
            $table->json('keywords')->nullable();
            $table->string('language', 50)->nullable();
            $table->string('edition', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['category_id', 'title']);
            $table->index('publication_year');
        });

        Schema::create('author_book', function (Blueprint $table) {
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();
            $table->primary(['book_id', 'author_id']);
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('inventory_number', 32)->unique();
            $table->string('barcode_value', 80)->unique();
            $table->string('barcode_symbology', 20)->default('code128');
            $table->string('condition', 20)->default('good');
            $table->string('status', 30)->default('available');
            $table->timestamp('registered_at');
            $table->text('notes')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['book_id', 'status']);
            $table->index(['location_id', 'status']);
        });

        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50);
            $table->string('scope', 20);
            $table->unsignedBigInteger('current_value')->default(0);
            $table->timestamps();
            $table->unique(['key', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('copies');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('author_book');
        Schema::dropIfExists('books');
        Schema::dropIfExists('authors');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('student_cards');
        Schema::dropIfExists('students');
    }
};

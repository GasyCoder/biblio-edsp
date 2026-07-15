<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/students', [StudentController::class, 'index'])->middleware('permission:students.view')->name('students.index');
    Route::get('/students/create', [StudentController::class, 'create'])->middleware('permission:students.manage')->name('students.create');
    Route::post('/students', [StudentController::class, 'store'])->middleware('permission:students.manage')->name('students.store');

    Route::get('/books', [BookController::class, 'index'])->middleware('permission:books.view|catalog.view')->name('books.index');
    Route::get('/books/create', [BookController::class, 'create'])->middleware('permission:books.create')->name('books.create');
    Route::post('/books', [BookController::class, 'store'])->middleware('permission:books.create')->name('books.store');
});

require __DIR__.'/auth.php';

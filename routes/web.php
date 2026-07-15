<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\CatalogReferenceController;
use App\Http\Controllers\CopyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeskController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StudentCardController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentImportController;
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
    Route::get('/student-imports', [StudentImportController::class, 'index'])->middleware('permission:imports.view')->name('student-imports.index');
    Route::post('/student-imports', [StudentImportController::class, 'store'])->middleware('permission:imports.upload')->name('student-imports.store');
    Route::get('/student-imports/{import}', [StudentImportController::class, 'show'])->middleware('permission:imports.review')->name('student-imports.show');
    Route::post('/student-imports/{import}/commit', [StudentImportController::class, 'commit'])->middleware('permission:imports.commit')->name('student-imports.commit');
    Route::get('/student-exports/xlsx', [StudentImportController::class, 'export'])->middleware('permission:imports.view')->name('student-exports.xlsx');

    Route::get('/books', [BookController::class, 'index'])->middleware('permission:books.view|catalog.view')->name('books.index');
    Route::get('/books/create', [BookController::class, 'create'])->middleware('permission:books.create')->name('books.create');
    Route::post('/books', [BookController::class, 'store'])->middleware('permission:books.create')->name('books.store');

    Route::get('/catalog-references', [CatalogReferenceController::class, 'index'])->middleware('permission:categories.view|authors.view|locations.view')->name('catalog-references.index');
    Route::post('/categories', [CatalogReferenceController::class, 'storeCategory'])->middleware('permission:categories.create')->name('categories.store');
    Route::post('/authors', [CatalogReferenceController::class, 'storeAuthor'])->middleware('permission:authors.create')->name('authors.store');
    Route::post('/locations', [CatalogReferenceController::class, 'storeLocation'])->middleware('permission:locations.create')->name('locations.store');

    Route::get('/copies', [CopyController::class, 'index'])->middleware('permission:copies.view')->name('copies.index');
    Route::get('/copies/create', [CopyController::class, 'create'])->middleware('permission:copies.create')->name('copies.create');
    Route::post('/copies', [CopyController::class, 'store'])->middleware('permission:copies.create')->name('copies.store');
    Route::get('/copies/{copy}/print', [CopyController::class, 'print'])->middleware('permission:copies.print')->name('copies.print');

    Route::get('/cards', [StudentCardController::class, 'index'])->middleware('permission:cards.view')->name('cards.index');
    Route::get('/cards/create', [StudentCardController::class, 'create'])->middleware('permission:cards.create')->name('cards.create');
    Route::post('/cards', [StudentCardController::class, 'store'])->middleware('permission:cards.create')->name('cards.store');
    Route::get('/cards/{card}/print', [StudentCardController::class, 'print'])->middleware('permission:cards.print')->name('cards.print');

    Route::get('/desk', [DeskController::class, 'index'])->middleware('permission:cards.scan')->name('desk.index');
    Route::post('/desk/students/{student}/check-in', [DeskController::class, 'checkIn'])->middleware('permission:visits.check_in')->name('desk.check-in');
    Route::post('/desk/visits/{visit}/check-out', [DeskController::class, 'checkOut'])->middleware('permission:visits.check_out')->name('desk.check-out');
    Route::post('/desk/visits/{visit}/consultation', [DeskController::class, 'openConsultation'])->middleware('permission:consultations.open')->name('desk.consultations.open');
    Route::post('/desk/consultations/{session}/copies', [DeskController::class, 'addCopy'])->middleware('permission:consultations.add_copy')->name('desk.consultations.copies.store');
    Route::post('/desk/consultation-items/{item}/return', [DeskController::class, 'returnCopy'])->middleware('permission:consultations.return_copy')->name('desk.consultations.copies.return');
    Route::post('/desk/consultations/{session}/close', [DeskController::class, 'closeConsultation'])->middleware('permission:consultations.close')->name('desk.consultations.close');
});

require __DIR__.'/auth.php';

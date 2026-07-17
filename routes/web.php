<?php

use App\Http\Controllers\AiImageController;
use App\Http\Controllers\AttendanceStationController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BookImportController;
use App\Http\Controllers\CatalogReferenceController;
use App\Http\Controllers\CopyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeskController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StudentCardController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VisitController;
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
    Route::delete('/students/bulk', [StudentController::class, 'destroyBulk'])->middleware('permission:students.manage')->name('students.destroy.bulk');
    Route::get('/students/{student}', [StudentController::class, 'show'])->middleware('permission:students.view')->name('students.show');
    Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->middleware('permission:students.update')->name('students.edit');
    Route::patch('/students/{student}', [StudentController::class, 'update'])->middleware('permission:students.update')->name('students.update');
    Route::delete('/students/{student}', [StudentController::class, 'destroy'])->middleware('permission:students.manage')->name('students.destroy');
    Route::get('/student-imports', [StudentImportController::class, 'index'])->middleware('permission:imports.view')->name('student-imports.index');
    Route::post('/student-imports', [StudentImportController::class, 'store'])->middleware('permission:imports.upload')->name('student-imports.store');
    Route::get('/student-imports/{import}', [StudentImportController::class, 'show'])->middleware('permission:imports.review')->name('student-imports.show');
    Route::post('/student-imports/{import}/commit', [StudentImportController::class, 'commit'])->middleware('permission:imports.commit')->name('student-imports.commit');
    Route::get('/student-exports/xlsx', [StudentImportController::class, 'export'])->middleware('permission:imports.view')->name('student-exports.xlsx');

    Route::get('/books', [BookController::class, 'index'])->middleware('permission:books.view|catalog.view')->name('books.index');
    Route::get('/books/create', [BookController::class, 'create'])->middleware('permission:books.create')->name('books.create');
    Route::post('/books', [BookController::class, 'store'])->middleware('permission:books.create')->name('books.store');
    Route::post('/api/ai/images', AiImageController::class)->middleware('throttle:5,1')->name('ai.images.generate');
    Route::delete('/books/bulk', [BookController::class, 'destroyBulk'])->middleware('permission:catalog.manage')->name('books.destroy.bulk');
    Route::get('/books/{book}', [BookController::class, 'show'])->middleware('permission:books.view|catalog.view')->name('books.show');
    Route::get('/books/{book}/edit', [BookController::class, 'edit'])->middleware('permission:books.update')->name('books.edit');
    Route::patch('/books/{book}', [BookController::class, 'update'])->middleware('permission:books.update')->name('books.update');
    Route::delete('/books/{book}', [BookController::class, 'destroy'])->middleware('permission:catalog.manage')->name('books.destroy');
    Route::get('/book-imports', [BookImportController::class, 'index'])->middleware('permission:imports.view')->name('book-imports.index');
    Route::post('/book-imports', [BookImportController::class, 'store'])->middleware('permission:imports.upload')->name('book-imports.store');
    Route::get('/book-imports/{import}', [BookImportController::class, 'show'])->middleware('permission:imports.review')->name('book-imports.show');
    Route::post('/book-imports/{import}/commit', [BookImportController::class, 'commit'])->middleware('permission:imports.commit')->name('book-imports.commit');
    Route::get('/book-exports/xlsx', [BookImportController::class, 'export'])->middleware('permission:imports.view')->name('book-exports.xlsx');

    Route::get('/catalog-references', [CatalogReferenceController::class, 'index'])->middleware('permission:categories.view|authors.view|locations.view')->name('catalog-references.index');
    Route::get('/categories', [CatalogReferenceController::class, 'categories'])->middleware('permission:categories.view')->name('categories.index');
    Route::post('/categories', [CatalogReferenceController::class, 'storeCategory'])->middleware('permission:categories.create')->name('categories.store');
    Route::delete('/categories/bulk', [CatalogReferenceController::class, 'destroyCategoriesBulk'])->middleware('permission:catalog.manage')->name('categories.destroy.bulk');
    Route::patch('/categories/{category}', [CatalogReferenceController::class, 'updateCategory'])->middleware('permission:categories.update')->name('categories.update');
    Route::delete('/categories/{category}', [CatalogReferenceController::class, 'destroyCategory'])->middleware('permission:catalog.manage')->name('categories.destroy');
    Route::post('/authors', [CatalogReferenceController::class, 'storeAuthor'])->middleware('permission:authors.create')->name('authors.store');
    Route::get('/authors', [CatalogReferenceController::class, 'authors'])->middleware('permission:authors.view')->name('authors.index');
    Route::delete('/authors/bulk', [CatalogReferenceController::class, 'destroyAuthorsBulk'])->middleware('permission:catalog.manage')->name('authors.destroy.bulk');
    Route::patch('/authors/{author}', [CatalogReferenceController::class, 'updateAuthor'])->middleware('permission:authors.update')->name('authors.update');
    Route::delete('/authors/{author}', [CatalogReferenceController::class, 'destroyAuthor'])->middleware('permission:catalog.manage')->name('authors.destroy');
    Route::post('/locations', [CatalogReferenceController::class, 'storeLocation'])->middleware('permission:locations.create')->name('locations.store');
    Route::get('/locations', [CatalogReferenceController::class, 'locations'])->middleware('permission:locations.view')->name('locations.index');
    Route::patch('/locations/{location}', [CatalogReferenceController::class, 'updateLocation'])->middleware('permission:locations.update')->name('locations.update');
    Route::delete('/locations/{location}', [CatalogReferenceController::class, 'destroyLocation'])->middleware('permission:catalog.manage')->name('locations.destroy');

    Route::get('/copies', [CopyController::class, 'index'])->middleware('permission:copies.view')->name('copies.index');
    Route::get('/copies/create', [CopyController::class, 'create'])->middleware('permission:copies.create')->name('copies.create');
    Route::post('/copies', [CopyController::class, 'store'])->middleware('permission:copies.create')->name('copies.store');
    Route::get('/copies-print/bulk', [CopyController::class, 'printBulk'])->middleware('permission:copies.print')->name('copies.print.bulk');
    Route::get('/copies-print/pdf', [CopyController::class, 'downloadPdf'])->middleware('permission:copies.print')->name('copies.print.pdf');
    Route::delete('/copies/bulk', [CopyController::class, 'destroyBulk'])->middleware('permission:catalog.manage')->name('copies.destroy.bulk');
    Route::get('/copies/{copy}/edit', [CopyController::class, 'edit'])->middleware('permission:copies.update')->name('copies.edit');
    Route::patch('/copies/{copy}', [CopyController::class, 'update'])->middleware('permission:copies.update')->name('copies.update');
    Route::delete('/copies/{copy}', [CopyController::class, 'destroy'])->middleware('permission:catalog.manage')->name('copies.destroy');
    Route::get('/copies/{copy}/print', [CopyController::class, 'print'])->middleware('permission:copies.print')->name('copies.print');

    Route::get('/cards', [StudentCardController::class, 'index'])->middleware('permission:cards.view')->name('cards.index');
    Route::get('/cards/create', [StudentCardController::class, 'create'])->middleware('permission:cards.create')->name('cards.create');
    Route::post('/cards', [StudentCardController::class, 'store'])->middleware('permission:cards.create')->name('cards.store');
    Route::get('/cards-print/bulk', [StudentCardController::class, 'printBulk'])->middleware('permission:cards.print')->name('cards.print.bulk');
    Route::delete('/cards/bulk', [StudentCardController::class, 'destroyBulk'])->middleware('permission:cards.manage')->name('cards.destroy.bulk');
    Route::get('/cards/{card}/edit', [StudentCardController::class, 'edit'])->middleware('permission:cards.update')->name('cards.edit');
    Route::patch('/cards/{card}', [StudentCardController::class, 'update'])->middleware('permission:cards.update')->name('cards.update');
    Route::delete('/cards/{card}', [StudentCardController::class, 'destroy'])->middleware('permission:cards.manage')->name('cards.destroy');
    Route::get('/cards/{card}/print', [StudentCardController::class, 'print'])->middleware('permission:cards.print')->name('cards.print');

    Route::get('/desk', [DeskController::class, 'index'])->middleware('permission:cards.scan')->name('desk.index');
    Route::post('/desk/identify', [DeskController::class, 'identify'])->middleware(['permission:cards.scan', 'permission:visits.check_in'])->name('desk.identify');
    Route::get('/attendance/{mode}', [AttendanceStationController::class, 'show'])->middleware('permission:cards.scan')->name('attendance.station');
    Route::post('/attendance/{mode}/scan', [AttendanceStationController::class, 'scan'])->middleware('permission:visits.check_in|visits.check_out')->name('attendance.scan');
    Route::get('/visits', [VisitController::class, 'index'])->middleware('permission:visits.view|visits.view_own')->name('visits.index');
    Route::get('/visits-export/xlsx', [VisitController::class, 'exportExcel'])->middleware('permission:reports.operational|reports.export')->name('visits.export.xlsx');
    Route::get('/visits-export/pdf', [VisitController::class, 'exportPdf'])->middleware('permission:reports.operational|reports.export')->name('visits.export.pdf');
    Route::get('/visits-print', [VisitController::class, 'print'])->middleware('permission:reports.operational|reports.export')->name('visits.print');
    Route::get('/loans', [LoanController::class, 'index'])->middleware('permission:loans.view|loans.view_own')->name('loans.index');
    Route::get('/reports', [ReportController::class, 'index'])->middleware('permission:reports.operational|reports.statistics')->name('reports.index');
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.manage')->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->middleware('permission:users.manage')->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.manage')->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:users.manage')->name('users.edit');
    Route::patch('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.manage')->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.manage')->name('users.destroy');
    Route::get('/settings', [SettingController::class, 'edit'])->middleware('permission:settings.manage')->name('settings.edit');
    Route::patch('/settings', [SettingController::class, 'update'])->middleware('permission:settings.manage')->name('settings.update');
    Route::post('/desk/students/{student}/check-in', [DeskController::class, 'checkIn'])->middleware('permission:visits.check_in')->name('desk.check-in');
    Route::post('/desk/visits/{visit}/check-out', [DeskController::class, 'checkOut'])->middleware('permission:visits.check_out')->name('desk.check-out');
    Route::post('/desk/visits/{visit}/complete', [DeskController::class, 'completeVisit'])->middleware(['permission:visits.check_out', 'permission:consultations.close'])->name('desk.visits.complete');
    Route::post('/desk/visits/{visit}/consultation', [DeskController::class, 'openConsultation'])->middleware('permission:consultations.open')->name('desk.consultations.open');
    Route::post('/desk/consultations/{session}/copies', [DeskController::class, 'addCopy'])->middleware('permission:consultations.add_copy')->name('desk.consultations.copies.store');
    Route::post('/desk/consultation-items/{item}/return', [DeskController::class, 'returnCopy'])->middleware('permission:consultations.return_copy')->name('desk.consultations.copies.return');
    Route::post('/desk/consultations/{session}/close', [DeskController::class, 'closeConsultation'])->middleware('permission:consultations.close')->name('desk.consultations.close');
    Route::post('/desk/students/{student}/loans', [DeskController::class, 'openLoan'])->middleware('permission:loans.create')->name('desk.loans.open');
    Route::post('/desk/loans/{loan}/copies', [DeskController::class, 'addLoanCopy'])->middleware('permission:loans.create')->name('desk.loans.copies.store');
    Route::post('/desk/loan-items/{item}/return', [DeskController::class, 'returnLoanCopy'])->middleware('permission:loans.return')->name('desk.loans.copies.return');
    Route::post('/desk/loans/{loan}/close', [DeskController::class, 'closeLoan'])->middleware('permission:loans.close')->name('desk.loans.close');
});

require __DIR__.'/auth.php';

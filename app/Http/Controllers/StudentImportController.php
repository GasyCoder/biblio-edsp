<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Services\StudentService;
use App\Services\StudentSpreadsheetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentImportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Students/Imports/Index', [
            'imports' => ImportBatch::query()->where('type', 'students')->with('uploader:id,name')->latest()->paginate(15),
        ]);
    }

    public function store(Request $request, StudentSpreadsheetService $spreadsheets): RedirectResponse
    {
        $validated = $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']]);
        $import = $spreadsheets->preview($validated['file'], $request->user());

        return to_route('student-imports.show', $import)->with('success', 'Fichier analysé. Vérifiez les lignes avant validation.');
    }

    public function show(ImportBatch $import): Response
    {
        abort_unless($import->type === 'students', 404);

        return Inertia::render('Students/Imports/Show', [
            'importBatch' => $import,
            'rows' => $import->rows()->orderBy('row_number')->paginate(30),
            'canCommit' => request()->user()->can('imports.commit'),
        ]);
    }

    public function commit(Request $request, ImportBatch $import, StudentSpreadsheetService $spreadsheets, StudentService $students): RedirectResponse
    {
        abort_unless($import->type === 'students', 404);
        $spreadsheets->commit($import, $request->user(), $students);

        return to_route('student-imports.show', $import)->with('success', 'Les lignes valides ont été importées.');
    }

    public function export(StudentSpreadsheetService $spreadsheets): BinaryFileResponse
    {
        return $spreadsheets->export();
    }
}

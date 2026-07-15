<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Services\BookSpreadsheetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BookImportController extends Controller
{
    public function index(BookSpreadsheetService $spreadsheets): Response
    {
        return Inertia::render('Books/Imports/Index', ['imports' => ImportBatch::query()->where('type', 'books')->with('uploader:id,name')->latest()->paginate(15), 'referenceFiles' => $spreadsheets->referenceFiles()]);
    }

    public function store(Request $request, BookSpreadsheetService $spreadsheets): RedirectResponse
    {
        $validated = $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480']]);
        $import = $spreadsheets->previewUpload($validated['file'], $request->user());

        return to_route('book-imports.show', $import)->with('success', 'Classeur analysé. Vérifiez les ouvrages et quantités.');
    }

    public function reference(Request $request, BookSpreadsheetService $spreadsheets): RedirectResponse
    {
        $validated = $request->validate(['filename' => ['required', 'string']]);
        $import = $spreadsheets->previewReference($validated['filename'], $request->user());

        return to_route('book-imports.show', $import)->with('success', 'Fichier de référence analysé.');
    }

    public function show(ImportBatch $import): Response
    {
        abort_unless($import->type === 'books', 404);

        return Inertia::render('Books/Imports/Show', ['importBatch' => $import, 'rows' => $import->rows()->orderBy('row_number')->paginate(30), 'canCommit' => request()->user()->can('imports.commit')]);
    }

    public function commit(Request $request, ImportBatch $import, BookSpreadsheetService $spreadsheets): RedirectResponse
    {
        abort_unless($import->type === 'books', 404);
        $spreadsheets->commit($import, $request->user());

        return to_route('book-imports.show', $import)->with('success', 'Ouvrages et exemplaires créés.');
    }

    public function export(BookSpreadsheetService $spreadsheets): BinaryFileResponse
    {
        return $spreadsheets->export();
    }
}

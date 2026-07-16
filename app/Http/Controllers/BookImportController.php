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
    public function index(): Response
    {
        return Inertia::render('Books/Imports/Index', [
            'imports' => ImportBatch::query()->where('type', 'books')->with('uploader:id,name')->latest()->paginate(15),
        ]);
    }

    public function store(Request $request, BookSpreadsheetService $spreadsheets): RedirectResponse
    {
        if ($request->hasFile('files')) {
            $validated = $request->validate([
                'files' => ['required', 'array', 'min:1', 'max:20'],
                'files.*' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
            ]);
            $files = $validated['files'];
        } else {
            // Compatibilité avec l'ancien formulaire et les intégrations existantes.
            $validated = $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480']]);
            $files = [$validated['file']];
        }

        $imports = collect($files)
            ->map(fn ($file) => $spreadsheets->previewUpload($file, $request->user()));

        if ($imports->count() === 1) {
            return to_route('book-imports.show', $imports->first())
                ->with('success', 'Classeur analysé. Vérifiez les ouvrages et quantités.');
        }

        return to_route('book-imports.index')
            ->with('success', $imports->count().' classeurs analysés. Contrôlez chaque import avant validation.');
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

<?php

namespace App\Services;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BookSpreadsheetService
{
    public function __construct(private readonly CopyService $copies, private readonly CategoryCodeService $categoryCodes) {}

    public function previewUpload(UploadedFile $file, User $user): ImportBatch
    {
        $path = $file->storeAs('imports/books/'.now()->format('Y/m'), Str::ulid().'.'.$file->getClientOriginalExtension(), 'local');

        return $this->previewPath(Storage::disk('local')->path($path), $path, $file->getClientOriginalName(), $user);
    }

    public function previewReference(string $filename, User $user): ImportBatch
    {
        $available = $this->referenceFiles();
        abort_unless(in_array($filename, $available, true), 404);
        $source = storage_path('app/imports/reference/'.$filename);
        $path = 'imports/books/'.now()->format('Y/m').'/'.Str::ulid().'.xlsx';
        Storage::disk('local')->put($path, file_get_contents($source));

        return $this->previewPath(Storage::disk('local')->path($path), $path, $filename, $user);
    }

    /** @return list<string> */
    public function referenceFiles(): array
    {
        return collect(glob(storage_path('app/imports/reference/*')) ?: [])
            ->filter(fn (string $path) => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['xlsx', 'xls', 'csv'], true))
            ->map(fn (string $path) => basename($path))->sort()->values()->all();
    }

    public function commit(ImportBatch $import, User $user): void
    {
        DB::transaction(function () use ($import, $user): void {
            $locked = ImportBatch::query()->lockForUpdate()->findOrFail($import->id);
            if ($locked->type !== 'books' || ! in_array($locked->status, ['ready', 'needs_review'], true)) {
                throw ValidationException::withMessages(['import' => 'Cet import ne peut plus être validé.']);
            }

            $count = 0;
            foreach ($locked->rows()->where('status', 'valid')->get() as $row) {
                $data = $row->normalized_data;
                $category = null;
                if ($data['category']) {
                    $category = Category::withTrashed()->firstOrCreate(['slug' => Str::slug($data['category'])], ['name' => $data['category'], 'inventory_code' => $this->categoryCodes->generate($data['category']), 'is_active' => true]);
                    if ($category->trashed()) {
                        $category->restore();
                    }
                }
                $book = Book::create(['category_id' => $category?->id, 'title' => $data['title'], 'publisher' => $data['publisher'], 'publication_year' => $data['publication_year'], 'language' => 'Français']);
                foreach ($data['authors'] as $position => $name) {
                    $author = Author::create(['display_name' => $name]);
                    $book->authors()->attach($author, ['position' => $position + 1]);
                }
                for ($i = 0; $i < $data['quantity']; $i++) {
                    $this->copies->create(['book_id' => $book->id, 'notes' => 'Import : '.$locked->original_filename]);
                }
                $row->update(['status' => 'imported', 'book_id' => $book->id]);
                $count++;
            }
            $locked->update(['status' => 'committed', 'imported_rows' => $count, 'committed_by' => $user->id, 'committed_at' => now()]);
        }, 3);
    }

    public function export(): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $headers = ['Numero', 'Categorie', "Titre de l'Ouvrage", "Année et Maison d'Edition", 'Auteur', "Nombre d'ouvrage", "Numéro d'enregistrement"];
        $sheet->fromArray([$headers], null, 'A1');
        $row = 2;
        Book::with(['category', 'authors', 'copies'])->orderBy('title')->each(function (Book $book) use ($sheet, &$row) {
            $values = [$book->id, $book->category?->name, $book->title, trim(implode(' ', array_filter([$book->publisher, $book->publication_year]))), $book->authors->pluck('display_name')->join(' / '), $book->copies->count(), $book->copies->pluck('inventory_number')->join(', ')];
            foreach ($values as $index => $value) {
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1).$row, (string) ($value ?? ''), DataType::TYPE_STRING);
            }
            $row++;
        });
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $path = Storage::disk('local')->path('exports/ouvrages-'.now()->format('Ymd-His').'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, basename($path))->deleteFileAfterSend(true);
    }

    private function previewPath(string $absolutePath, string $storedPath, string $filename, User $user): ImportBatch
    {
        try {
            $spreadsheet = IOFactory::load($absolutePath);
        } catch (\Throwable) {
            Storage::disk('local')->delete($storedPath);
            throw ValidationException::withMessages(['file' => 'Le classeur est illisible ou corrompu.']);
        }

        return DB::transaction(function () use ($spreadsheet, $storedPath, $filename, $user): ImportBatch {
            $import = ImportBatch::create(['type' => 'books', 'original_filename' => $filename, 'stored_path' => $storedPath, 'status' => 'processing', 'uploaded_by' => $user->id]);
            foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                $headerRow = $this->findHeaderRow($sheet);
                if (! $headerRow) {
                    continue;
                }
                $headers = $this->headers($sheet, $headerRow);
                $category = null;
                $current = null;
                for ($row = $headerRow + 1; $row <= $sheet->getHighestDataRow(); $row++) {
                    $values = $this->rowValues($sheet, $row, $headers);
                    if ($values['category'] ?? null) {
                        $category = trim((string) $values['category']);
                    }
                    if ($values['title'] ?? null) {
                        if ($current) {
                            $this->saveRow($import, $current);
                        }
                        $current = ['row_number' => $row, 'sheet' => $sheet->getTitle(), 'source_rows' => [$values], 'category' => $category, 'title' => trim((string) $values['title']), 'publication' => trim((string) ($values['publication'] ?? '')), 'authors' => [], 'quantity' => $this->quantity($values['quantity'] ?? null)];
                    } elseif ($current) {
                        $current['source_rows'][] = $values;
                        $current['quantity'] ??= $this->quantity($values['quantity'] ?? null);
                    }
                    if ($current && ($values['author'] ?? null)) {
                        $current['authors'] = [...$current['authors'], ...$this->authors((string) $values['author'])];
                    }
                }
                if ($current) {
                    $this->saveRow($import, $current);
                }
            }
            $total = $import->rows()->count();
            $errors = $import->rows()->where('status', 'error')->count();
            $import->update(['sheet_name' => $spreadsheet->getSheetCount() === 1 ? $spreadsheet->getActiveSheet()->getTitle() : $spreadsheet->getSheetCount().' feuilles', 'status' => $errors ? 'needs_review' : 'ready', 'total_rows' => $total, 'valid_rows' => $total - $errors, 'error_rows' => $errors]);

            return $import->refresh();
        });
    }

    private function saveRow(ImportBatch $import, array $data): void
    {
        $authors = array_values(array_unique(array_filter(array_map('trim', $data['authors']))));
        preg_match_all('/\b(1[0-9]{3}|20[0-9]{2})\b/', $data['publication'], $years);
        $year = $years[1] ? (int) end($years[1]) : null;
        $normalized = ['category' => $data['category'], 'title' => $data['title'], 'publisher' => $data['publication'] ?: null, 'publication_year' => $year, 'authors' => $authors, 'quantity' => $data['quantity']];
        $errors = [];
        if (! $authors) {
            $errors[] = 'Auteur absent ou ambigu.';
        }
        if (! is_int($data['quantity']) || $data['quantity'] < 1 || $data['quantity'] > 1000) {
            $errors[] = 'Quantité absente, ambiguë ou invalide.';
        }
        $import->rows()->create(['row_number' => $data['row_number'], 'original_data' => ['sheet' => $data['sheet'], 'rows' => $data['source_rows']], 'normalized_data' => $normalized, 'errors' => $errors ?: null, 'status' => $errors ? 'error' : 'valid']);
    }

    private function findHeaderRow($sheet): ?int
    {
        for ($row = 1; $row <= min(20, $sheet->getHighestDataRow()); $row++) {
            $values = array_map(fn ($value) => $this->key((string) $value), $sheet->rangeToArray("A{$row}:{$sheet->getHighestDataColumn()}{$row}", null, true, true, false)[0]);
            if (collect($values)->contains(fn ($value) => str_contains($value, 'titre'))) {
                return $row;
            }
        }

        return null;
    }

    private function headers($sheet, int $row): array
    {
        $result = [];
        foreach ($sheet->rangeToArray("A{$row}:{$sheet->getHighestDataColumn()}{$row}", null, true, true, false)[0] as $index => $value) {
            $key = $this->key((string) $value);
            $field = match (true) {
                str_contains($key, 'categorie') => 'category', str_contains($key, 'titre') => 'title', str_contains($key, 'annee') || str_contains($key, 'edition') => 'publication', str_contains($key, 'auteur') => 'author', str_contains($key, 'nombre') || str_contains($key, 'quantite') => 'quantity', default => null
            };
            if ($field) {
                $result[$index + 1] = $field;
            }
        }

        return $result;
    }

    private function rowValues($sheet, int $row, array $headers): array
    {
        $values = [];
        foreach ($headers as $column => $field) {
            $values[$field] = $sheet->getCell([$column, $row])->getFormattedValue();
        }

        return $values;
    }

    private function authors(string $value): array
    {
        return preg_split('/\s*[\/,;]\s*/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private function quantity(mixed $value): ?int
    {
        return is_numeric(trim((string) $value)) ? (int) $value : null;
    }

    private function key(string $value): string
    {
        return (string) Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_');
    }
}

<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentSpreadsheetService
{
    private const HEADERS = ['matricule', 'nom', 'prenom', 'sexe', 'code_redoublement', 'date_naissance', 'nationalite', 'telephone', 'adresse', 'mention', 'parcours', 'niveau', 'annee_universitaire', 'email'];

    private const ALIASES = [
        'matricule' => 'academic_number', 'numero_matricule' => 'academic_number',
        'nom' => 'last_name', 'prenom' => 'first_name', 'sexe' => 'gender',
        'code_redoublement' => 'repetition_code', 'redoublement' => 'repetition_code',
        'date_naissance' => 'birth_date', 'nationalite' => 'nationality',
        'telephone' => 'phone', 'adresse' => 'address', 'mention' => 'mention', 'niveau' => 'level',
        'parcours' => 'program', 'annee_universitaire' => 'academic_year', 'email' => 'email',
    ];

    public function __construct(private readonly AcademicReferenceService $academicReferences) {}

    public function preview(UploadedFile $file, User $user): ImportBatch
    {
        $path = $file->storeAs('imports/students/'.now()->format('Y/m'), Str::ulid().'.'.$file->getClientOriginalExtension(), 'local');
        try {
            $spreadsheet = IOFactory::load(Storage::disk('local')->path($path));
        } catch (\Throwable) {
            Storage::disk('local')->delete($path);
            throw ValidationException::withMessages(['file' => 'Le fichier est illisible ou corrompu.']);
        }
        $sheet = $spreadsheet->getActiveSheet();
        $rawRows = $sheet->toArray(null, true, true, false);
        $headers = array_map(fn ($value) => $this->normalizeHeader((string) $value), array_shift($rawRows) ?? []);
        $seen = [];

        return DB::transaction(function () use ($file, $user, $path, $sheet, $rawRows, $headers, &$seen): ImportBatch {
            $import = ImportBatch::create([
                'type' => 'students', 'original_filename' => $file->getClientOriginalName(), 'stored_path' => $path,
                'sheet_name' => $sheet->getTitle(), 'status' => 'processing', 'uploaded_by' => $user->id,
            ]);

            foreach ($rawRows as $offset => $values) {
                $original = array_filter(array_combine($headers, array_pad($values, count($headers), null)) ?: [], fn ($value) => $value !== null && $value !== '');
                if ($original === []) {
                    continue;
                }

                $normalized = $this->normalizeRow($original);
                $academicErrors = [];
                try {
                    $normalized = $this->academicReferences->resolve($normalized);
                } catch (ValidationException $exception) {
                    $academicErrors = $exception->errors();
                }
                $validator = Validator::make($normalized, [
                    'academic_number' => ['required', 'string', 'max:64'], 'last_name' => ['required', 'string', 'max:255'],
                    'first_name' => ['nullable', 'string', 'max:255'], 'gender' => ['nullable', Rule::in(['male', 'female'])],
                    'repetition_code' => ['required', Rule::in(['N', 'R', 'T'])], 'birth_date' => ['nullable', 'date', 'before:today'],
                    'nationality' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'],
                    'address' => ['nullable', 'string', 'max:2000'], 'mention' => ['nullable', 'string', 'max:150'], 'level' => ['nullable', 'string', 'max:100'],
                    'program' => ['nullable', 'string', 'max:150'], 'academic_year' => ['nullable', 'string', 'max:20'],
                    'email' => ['nullable', 'email', 'max:255'],
                ]);
                $errors = [...$validator->errors()->all(), ...collect($academicErrors)->flatten()->all()];
                $matricule = $normalized['academic_number'] ?? null;
                if ($matricule && (isset($seen[$matricule]) || Student::withTrashed()->where('academic_number', $matricule)->exists())) {
                    $errors[] = isset($seen[$matricule]) ? 'Matricule présent plusieurs fois dans le fichier.' : 'Matricule déjà enregistré dans l’application.';
                }
                if ($matricule) {
                    $seen[$matricule] = true;
                }

                $import->rows()->create([
                    'row_number' => $offset + 2, 'original_data' => $original, 'normalized_data' => $normalized,
                    'errors' => $errors ?: null, 'status' => $errors ? 'error' : 'valid',
                ]);
            }

            $total = $import->rows()->count();
            $errors = $import->rows()->where('status', 'error')->count();
            $import->update(['status' => $errors ? 'needs_review' : 'ready', 'total_rows' => $total, 'valid_rows' => $total - $errors, 'error_rows' => $errors]);

            return $import->refresh();
        });
    }

    public function commit(ImportBatch $import, User $user, StudentService $students): void
    {
        DB::transaction(function () use ($import, $user, $students): void {
            $locked = ImportBatch::query()->lockForUpdate()->findOrFail($import->id);
            if (! in_array($locked->status, ['ready', 'needs_review'], true)) {
                throw ValidationException::withMessages(['import' => 'Cet import ne peut plus être validé.']);
            }

            $count = 0;
            foreach ($locked->rows()->where('status', 'valid')->get() as $row) {
                if (Student::withTrashed()->where('academic_number', $row->normalized_data['academic_number'])->exists()) {
                    $row->update(['status' => 'error', 'errors' => ['Matricule déjà enregistré avant la validation.']]);

                    continue;
                }
                $student = $students->create([...$row->normalized_data, 'first_name' => $row->normalized_data['first_name'] ?? '', 'status' => 'active']);
                $row->update(['status' => 'imported', 'student_id' => $student->id]);
                $count++;
            }
            $locked->update(['status' => 'committed', 'imported_rows' => $count, 'committed_by' => $user->id, 'committed_at' => now()]);
        }, 3);
    }

    public function export(): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Étudiants');
        $sheet->fromArray([['numero_interne', ...self::HEADERS]], null, 'A1');
        $row = 2;
        Student::query()->orderBy('last_name')->each(function (Student $student) use ($sheet, &$row) {
            $values = [$student->registration_number, $student->academic_number, $student->last_name, $student->first_name, match ($student->gender) {
                'male' => 'M', 'female' => 'F', default => null
            }, $student->repetition_code, $student->birth_date?->format('Y-m-d'), $student->nationality, $student->phone, $student->address, $student->mention?->name, $student->program, $student->level, $student->academic_year, $student->email];
            foreach ($values as $index => $value) {
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1).$row, (string) ($value ?? ''), DataType::TYPE_STRING);
            }
            $row++;
        });
        $sheet->getStyle('A1:O1')->getFont()->setBold(true);
        foreach (range('A', 'O') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $path = Storage::disk('local')->path('exports/etudiants-'.now()->format('Ymd-His').'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, basename($path))->deleteFileAfterSend(true);
    }

    private function normalizeHeader(string $value): string
    {
        return (string) Str::of($value)->ascii()->lower()->trim()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_');
    }

    /** @param array<string, mixed> $original */
    private function normalizeRow(array $original): array
    {
        $row = [];
        foreach ($original as $header => $value) {
            if (isset(self::ALIASES[$header])) {
                $row[self::ALIASES[$header]] = is_string($value) ? trim($value) : $value;
            }
        }
        $row['gender'] = match (strtoupper((string) ($row['gender'] ?? ''))) {
            'M' => 'male', 'F' => 'female', default => $row['gender'] ?? null
        };
        $row['repetition_code'] = strtoupper((string) ($row['repetition_code'] ?? 'N'));
        if (isset($row['birth_date']) && is_numeric($row['birth_date'])) {
            $row['birth_date'] = Date::excelToDateTimeObject($row['birth_date'])->format('Y-m-d');
        }
        foreach ($row as $key => $value) {
            if ($value === '') {
                $row[$key] = null;
            }
        }

        return $row;
    }
}

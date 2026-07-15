<?php

namespace App\Services;

use App\Enums\NumberType;
use App\Enums\StudentStatus;
use App\Models\Student;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StudentService
{
    public function __construct(private readonly NumberGenerator $numbers, private readonly AcademicReferenceService $academicReferences) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): Student
    {
        if (! empty($data['photo'])) {
            $data['photo_path'] = $data['photo']->store('photos/students', 'public');
        }
        $data = Arr::except($data, ['photo', 'remove_photo']);
        $data = $this->academicReferences->resolve($data);

        return DB::transaction(function () use ($data): Student {
            return Student::query()->create([
                ...Arr::except($data, ['registration_number']),
                'registration_number' => $this->numbers->next(NumberType::Student),
                'status' => $data['status'] ?? StudentStatus::Active,
            ]);
        }, 3);
    }
}

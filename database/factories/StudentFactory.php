<?php

namespace Database\Factories;

use App\Enums\StudentStatus;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Student> */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'registration_number' => 'BIB-'.now()->format('y').'-'.fake()->unique()->numerify('###'),
            'academic_number' => fake()->unique()->bothify('MAT-####??'),
            'last_name' => fake()->lastName(),
            'first_name' => fake()->firstName(),
            'level' => 'Licence 1',
            'program' => 'Droit',
            'academic_year' => now()->year.'-'.(now()->year + 1),
            'status' => StudentStatus::Active,
        ];
    }
}

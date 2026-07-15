<?php

namespace Database\Factories;

use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Book> */
class BookFactory extends Factory
{
    public function definition(): array
    {
        return ['title' => fake()->sentence(4), 'publication_year' => fake()->numberBetween(1980, now()->year), 'publisher' => fake()->company(), 'language' => 'Français'];
    }
}

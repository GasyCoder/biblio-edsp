<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Location> */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('RAY-##??'), 'name' => 'Rayon '.fake()->word(), 'is_active' => true];
    }
}

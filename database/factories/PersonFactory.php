<?php

namespace Database\Factories;

use App\Enums\PersonStatus;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'document_type' => 'cc',
            'document_number' => (string) fake()->unique()->numerify('##########'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->numerify('3#########'),
            'email' => fake()->unique()->safeEmail(),
            'city' => 'Cartagena',
            'status' => PersonStatus::Active,
        ];
    }
}

<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Part>
 */
class PartFactory extends Factory
{
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'ref' => fake()->firstName(),
            'designation' => fake()->lastName(),
            'quantity' => fake()->randomDigit(),
            'price_unit' => fake()->randomFloat(2, 0, 1000),
        ];
    }
}

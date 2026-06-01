<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class IpFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
   {
        // Format: 10.109.x.y (x = 0/1, y = 1-255)
        $y = $this->faker->numberBetween(1, 255);

        return [
            'ip_address' => "10.109.1.{$y}",
        ];
    }
}

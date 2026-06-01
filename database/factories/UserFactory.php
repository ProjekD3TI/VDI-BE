<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Pola NIM khas UNS: V + 2 digit prodi + 2 digit angkatan + 3 digit urutan
        // Kita generates angka acak 3 digit di belakang untuk variasi
        $randomNIM = 'V3423' . $this->faker->unique()->numberBetween(100, 199);

        return [
            'name' => $this->faker->name(),
            'username' => $this->faker->unique()->userName(),
            'role' => 'user', // Default langsung diset 'user'
            'email' => $this->faker->unique()->safeEmail(),
            'nim' => $randomNIM,
            'angkatan_id' => $this->faker->numberBetween(1, 5), // Menggenerates angka acak dari 1 sampai 5
        ];
    }
}

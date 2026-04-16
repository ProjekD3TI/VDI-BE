<?php

namespace Database\Seeders;

use App\Models\Angkatan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AngkatanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Angkatan::insert([
            ['angkatan' => 2023],
            ['angkatan' => 2024],
            ['angkatan' => 2025],
            ['angkatan' => 2026],
        ]);
    }
}

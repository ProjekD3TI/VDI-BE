<?php

namespace Database\Seeders;

use App\Models\IpAddress;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class IpSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ips = [];

        // Looping untuk x = 0 sampai 1
        for ($y = 1; $y <= 255; $y++) {
            $ips[] = [
                'ip_address' => "10.109.1.{$y}",
                'status'     => 'free',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Menggunakan insert bulk agar jauh lebih cepat daripada create() satu-satu
        IpAddress::insert($ips);
    }
}

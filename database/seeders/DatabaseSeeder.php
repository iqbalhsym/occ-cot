<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            TindakanSeeder::class,
            TindakanGolonganSeeder::class,
            PaketBmhpSeeder::class,
            AlatKhususSeeder::class,
            PasienSeeder::class,
        ]);
    }
}

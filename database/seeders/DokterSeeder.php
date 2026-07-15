<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DokterSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = database_path('seeders/dokter_data.json');
        if (!file_exists($jsonPath)) {
            $this->command->error("dokter_data.json not found.");
            return;
        }

        $items = json_decode(file_get_contents($jsonPath), true);
        
        DB::table('doctors')->truncate();
        $now = now()->toDateTimeString();

        foreach ($items as $item) {
            DB::table('doctors')->insert([
                'nama'        => $item['nama'],
                'nama_gelar'  => $item['nama_gelar'],
                'spesialis'   => $item['spesialis'] ?: null,
                'ksm'         => $item['ksm'] ?: null,
                'konsultan'   => $item['konsultan'] ?: null,
                'status'      => $item['status'] ?: null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        $this->command->info("Doctor table seeded with " . count($items) . " records.");
    }
}

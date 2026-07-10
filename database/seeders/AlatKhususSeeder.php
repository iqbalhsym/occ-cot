<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AlatKhususSeeder extends Seeder
{
    public function run(): void
    {
        $dataPath = database_path('seeders/alat_data.json');
        $items = json_decode(file_get_contents($dataPath), true);

        DB::table('alat_khusus')->truncate();
        $now = now()->toDateTimeString();

        $inserted = [];
        $count = 0;

        foreach ($items as $item) {
            $name = trim($item['nama']);
            if (isset($inserted[$name])) {
                continue;
            }
            $inserted[$name] = true;

            DB::table('alat_khusus')->insert([
                'nama' => $name,
                'tarif' => $item['tarif'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $count++;
        }

        $this->command->info('AlatKhusus seeded: ' . $count . ' unique records.');
    }
}

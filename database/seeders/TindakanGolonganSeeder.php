<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TindakanGolonganSeeder extends Seeder
{
    public function run(): void
    {
        $dataPath = database_path('seeders/tindakan_golongan_data.json');
        $items = json_decode(file_get_contents($dataPath), true);

        DB::table('tindakan_golongan')->truncate();
        $now = now()->toDateTimeString();

        $chunks = array_chunk($items, 500);
        foreach ($chunks as $chunk) {
            $rows = array_map(fn($item) => [
                'tindakan'   => $item['tindakan'],
                'operator'   => $item['operator'] ?? null,
                'golongan'   => $item['golongan'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk);
            DB::table('tindakan_golongan')->insert($rows);
        }

        $this->command->info('TindakanGolongan seeded: ' . count($items) . ' records.');
    }
}

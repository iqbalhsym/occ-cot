<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaketBmhpSeeder extends Seeder
{
    public function run(): void
    {
        $dataPath = database_path('seeders/paket_bmhp_data.json');
        $items = json_decode(file_get_contents($dataPath), true);

        DB::table('paket_bmhp')->truncate();
        $now = now()->toDateTimeString();

        $inserted = [];
        $count = 0;

        foreach ($items as $item) {
            $name = trim($item['nama']);
            if (isset($inserted[$name])) {
                continue;
            }
            $inserted[$name] = true;

            DB::table('paket_bmhp')->insert([
                'nama' => $name,
                'tarif' => $item['tarif'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $count++;
        }

        $this->command->info('PaketBMHP seeded: ' . $count . ' unique records.');
    }
}

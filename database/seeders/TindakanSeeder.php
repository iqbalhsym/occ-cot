<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TindakanSeeder extends Seeder
{
    public function run(): void
    {
        $dataPath = database_path('seeders/tindakan_data.json');
        $items = json_decode(file_get_contents($dataPath), true);

        DB::table('tindakan')->truncate();
        $now = now()->toDateTimeString();

        foreach ($items as $item) {
            DB::table('tindakan')->insert([
                'nama'          => $item['nama'],
                'golongan'      => $item['golongan'] ?? null,
                'spesialisasi'  => $item['spesialisasi'] ?? null,
                'paket'         => $item['paket'] ?? null,
                'paket_anestesi'=> $item['paket_anestesi'] ?? null,
                'alat'          => $item['alat'] ?? null,
                'harga_bpjs'    => null,
                'harga_umum'    => null,
                'bmhp'          => null,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }

        $this->command->info('Tindakan seeded: ' . count($items) . ' records.');
    }
}

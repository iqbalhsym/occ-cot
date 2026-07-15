<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PasienSeeder extends Seeder
{
    public function run(): void
    {
        $now = now()->toDateTimeString();
        $patients = [
            [
                'rm' => '12-34-56',
                'nama' => 'Budi Santoso',
                'jenis_kelamin' => 'L',
                'tgl_lahir' => '1985-05-15',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'rm' => '78-90-12',
                'nama' => 'Siti Aminah',
                'jenis_kelamin' => 'P',
                'tgl_lahir' => '1990-11-23',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'rm' => '34-56-78',
                'nama' => 'Rudi Hermawan',
                'jenis_kelamin' => 'L',
                'tgl_lahir' => '1972-08-05',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'rm' => '56-78-90',
                'nama' => 'Dewi Lestari',
                'jenis_kelamin' => 'P',
                'tgl_lahir' => '1998-02-14',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        ];

        DB::table('pasien')->truncate();
        DB::table('pasien')->insert($patients);
    }
}

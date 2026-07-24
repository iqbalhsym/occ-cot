<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AlkesKhusus;

class AlkesKhususSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = database_path('seeders/alkes_khusus.json');
        if (!file_exists($jsonPath)) {
            return;
        }

        $data = json_decode(file_get_contents($jsonPath), true);
        if (!$data) {
            return;
        }

        // List of all new OT Rooms
        $allRooms = ["OT 1", "OT 2", "OT 3", "OT 4", "OT 5", "OT 6", "Hybrid", "OT lt 5", "IGD", "Cathlab"];

        AlkesKhusus::truncate();

        foreach ($data as $row) {
            $nama = trim($row['Nama Alkes KHUSUS'] ?? '');
            if (empty($nama)) continue;

            $rule = trim($row['OT '] ?? '');
            $allowed = [];

            // Match logic:
            if (stripos($rule, 'semua OT') !== false) {
                // Starts with all rooms
                $allowed = $allRooms;

                // Handle exclusions
                if (stripos($rule, 'kecuali') !== false) {
                    if (preg_match('/kecuali\s+([^)]+)/i', $rule, $matches)) {
                        $exString = $matches[1];
                    } else {
                        $exString = $rule;
                    }

                    // Check for individual exclusions
                    if (stripos($exString, 'OT 2') !== false) {
                        $allowed = array_diff($allowed, ['OT 2']);
                    }
                    if (stripos($exString, 'cathlab') !== false) {
                        $allowed = array_diff($allowed, ['Cathlab']);
                    }
                    if (stripos($exString, 'Mater Lt. 5') !== false || stripos($exString, 'Mater Lt.5') !== false || stripos($exString, 'OT Mater Lt. 5') !== false || stripos($exString, 'OT lt 5') !== false || stripos($exString, 'Lt. 5') !== false) {
                        $allowed = array_diff($allowed, ['OT lt 5']);
                    }
                    if (stripos($exString, 'OT IGD') !== false || stripos($exString, 'IGD') !== false) {
                        $allowed = array_diff($allowed, ['IGD']);
                    }
                }
            } else {
                // Specific rooms are listed
                if (stripos($rule, 'OT 1') !== false) $allowed[] = 'OT 1';
                if (stripos($rule, 'OT 2') !== false) $allowed[] = 'OT 2';
                if (stripos($rule, 'OT 3') !== false) $allowed[] = 'OT 3';
                if (stripos($rule, 'OT 4') !== false) $allowed[] = 'OT 4';
                if (stripos($rule, 'OT 5') !== false) $allowed[] = 'OT 5';
                if (stripos($rule, 'OT 6') !== false) $allowed[] = 'OT 6';
                if (stripos($rule, 'Hybrid') !== false) $allowed[] = 'Hybrid';
                if (stripos($rule, 'OT lt 5') !== false || stripos($rule, 'Lt.5') !== false) $allowed[] = 'OT lt 5';
                if (stripos($rule, 'IGD') !== false) $allowed[] = 'IGD';
                if (stripos($rule, 'Cathlab') !== false) $allowed[] = 'Cathlab';
                
                // Handle ranges like "OT 4-6"
                if (stripos($rule, 'OT 4-6') !== false) {
                    $allowed = array_unique(array_merge($allowed, ['OT 4', 'OT 5', 'OT 6']));
                }
            }

            // Always format and ensure unique values, convert keys back to indexed array
            $allowed = array_values(array_unique($allowed));

            AlkesKhusus::create([
                'nama' => $nama,
                'aturan_ruangan' => $rule,
                'allowed_rooms' => $allowed
            ]);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Tindakan;
use App\Models\TindakanGolongan;
use App\Models\PaketBmhp;
use App\Models\AlatKhusus;
use Illuminate\Http\Request;

class TindakanController extends Controller
{
    public function getMasterData()
    {
        // Load master data arrays from JSON files for autocompletes
        $seederPath = database_path('seeders/');
        
        $lokasi = [];
        $penjamin = [];
        $spesialisasi = [];
        
        if (file_exists($seederPath . 'tindakan_data.json')) {
            $data = json_decode(file_get_contents($seederPath . 'tindakan_data.json'), true);
            // Extracted locations and penjamin can also come from master files
        }

        // We can load master lists from the generated JSON
        $alatPath = $seederPath . 'alat_data.json';
        $alat = file_exists($alatPath) ? array_column(json_decode(file_get_contents($alatPath), true), 'nama') : [];

        // Let's parse the master lists directly from the original HTML to make sure they are 100% accurate
        $htmlPath = database_path('seeders/Operation_Command_Center_COT_RSUI_v2.html');
        if (file_exists($htmlPath)) {
            $html = file_get_contents($htmlPath);
            preg_match('/const COT_DB\s*=\s*(\{.*?\});/s', $html, $matches);
            if (!empty($matches)) {
                $db = json_decode($matches[1], true);
                if (isset($db['master'])) {
                    $lokasi = $db['master']['lokasi'] ?? [];
                    $penjamin = $db['master']['penjamin'] ?? [];
                    $spesialisasi = $db['master']['spesialisasi'] ?? [];
                }
            }
        }

        // If the parsing fails or lists are empty, fallback to database values or simple arrays
        if (empty($lokasi)) {
            $lokasi = ['COT', 'OT IGD', 'Cathlab', 'Endoskopi', 'Ruang Operasi 1', 'Ruang Operasi 2'];
        }
        if (empty($penjamin)) {
            $penjamin = ['Umum', 'BPJS Kesehatan', 'BPJS Ketenagakerjaan', 'Admedika', 'Allianz', 'Prudential'];
        }
        if (empty($spesialisasi)) {
            $spesialisasi = ['Bedah', 'Urologi', 'Orthopedi', 'Kebidanan & Kandungan', 'Mata', 'THT-KL', 'Bedah Digestif', 'Bedah Anak', 'Anestesiologi'];
        }

        // Actions autocomplete list
        $tindakanList = Tindakan::pluck('nama')->toArray();
        $tindakanGolonganList = TindakanGolongan::pluck('tindakan')->toArray();
        $allTindakan = array_unique(array_merge($tindakanList, $tindakanGolonganList));
        sort($allTindakan);

        return response()->json([
            'lokasi' => $lokasi,
            'penjamin' => $penjamin,
            'spesialisasi' => $spesialisasi,
            'tindakan' => array_values($allTindakan),
            'alat' => $alat
        ]);
    }

    public function lookupTindakan(Request $request)
    {
        $nama = $request->query('nama');
        if (!$nama) {
            return response()->json(['success' => false, 'message' => 'Nama tindakan wajib diisi']);
        }

        // First find in Tindakan table (from sheet DATABASE)
        $tindakan = Tindakan::where('nama', $nama)->first();
        
        // Also look in TindakanGolongan (from sheet TINDAKAN+GOLONGAN)
        $tg = TindakanGolongan::where('tindakan', $nama)->first();

        // Load the actions from HTML script to retrieve pre-configured BMHP packages if matches
        $bmhp = [];
        $golongan = $tindakan ? $tindakan->golongan : ($tg ? $tg->golongan : 'NON GOLONGAN');
        $spesialisasi = $tindakan ? $tindakan->spesialisasi : ($tg ? $tg->operator : '');
        $paket = $tindakan ? $tindakan->paket : '';
        $hargaUmum = null;
        $hargaBpjs = null;

        $htmlPath = database_path('seeders/Operation_Command_Center_COT_RSUI_v2.html');
        if (file_exists($htmlPath)) {
            $html = file_get_contents($htmlPath);
            preg_match('/const COT_DB\s*=\s*(\{.*?\});/s', $html, $matches);
            if (!empty($matches)) {
                $db = json_decode($matches[1], true);
                if (isset($db['tindakan'])) {
                    foreach ($db['tindakan'] as $item) {
                        if (strcasecmp($item['nama'], $nama) === 0) {
                            $bmhp = $item['bmhp'] ?? [];
                            $hargaUmum = $item['hargaUmum'] ?? null;
                            $hargaBpjs = $item['hargaBPJS'] ?? null;
                            if (isset($item['golongan'])) $golongan = $item['golongan'];
                            if (isset($item['spesialisasi'])) $spesialisasi = $item['spesialisasi'];
                            if (isset($item['paket'])) $paket = $item['paket'];
                            break;
                        }
                    }
                }
            }
        }

        // If no pre-configured BMHP list is found, let's try to load BMHP by package name from sheet PAKET BMHP
        if (empty($bmhp) && $paket) {
            $paketBmhp = PaketBmhp::where('nama', $paket)->first();
            if ($paketBmhp) {
                $bmhp = [
                    [
                        'n' => 'PAKET BMHP - ' . $paketBmhp->nama,
                        'h' => $paketBmhp->tarif,
                        'q' => 1.0
                    ]
                ];
                if (!$hargaUmum) {
                    $hargaUmum = $paketBmhp->tarif;
                }
            }
        }

        return response()->json([
            'success' => true,
            'nama' => $nama,
            'golongan' => strtoupper($golongan),
            'spesialisasi' => $spesialisasi,
            'paket' => $paket,
            'hargaUmum' => $hargaUmum,
            'hargaBPJS' => $hargaBpjs,
            'bmhp' => $bmhp
        ]);
    }
}

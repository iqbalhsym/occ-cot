<?php

namespace App\Http\Controllers;

use App\Models\Pasien;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PasienController extends Controller
{
    /**
     * Cari data pasien berdasarkan No RM.
     * Prioritas: API Bed Mon → fallback database lokal.
     */
    public function lookup($rm)
    {
        $apiUrl = env('BEDMON_API_URL');
        $apiKey = env('BEDMON_API_KEY');

        // ── Coba API eksternal Bed Mon jika konfigurasi tersedia ──
        if ($apiUrl && $apiKey && $apiUrl !== 'mock') {
            try {
                $response = Http::timeout(8)
                    ->withHeaders([
                        'X-API-Key' => $apiKey,
                    ])
                    ->get("{$apiUrl}/api/external/patient-by-nrm", [
                        'nrm' => $rm,
                    ]);

                if ($response->successful()) {
                    $result = $response->json();
                    
                    // Ambil data pasien (bisa di root atau di dalam key data/pasien)
                    $data = $result['data'] ?? $result['pasien'] ?? $result ?? null;

                    if ($data && is_array($data)) {
                        // Nama
                        $nama = $data['nama'] ?? $data['Nama'] ?? $data['name'] ?? $data['PatientName'] ?? $data['nama_pasien'] ?? '';

                        // Gender
                        $extGender = $data['jenis_kelamin'] ?? $data['Gender'] ?? $data['gender'] ?? $data['sex'] ?? null;
                        $jenisKelamin = 'L';
                        if (in_array(strtoupper($extGender), ['P', 'F', 'PEREMPUAN', 'WANITA', 'FEMALE'], true)) {
                            $jenisKelamin = 'P';
                        }

                        // Tanggal Lahir
                        $rawDob = $data['tgl_lahir'] ?? $data['TanggalLahir'] ?? $data['BirthDate'] ?? $data['date_of_birth'] ?? $data['dob'] ?? null;
                        $tglLahir = null;
                        if ($rawDob) {
                            $parsed = strtotime($rawDob);
                            if ($parsed !== false) {
                                $tglLahir = date('Y-m-d', $parsed);
                            }
                        }

                        $patientData = [
                            'rm'            => $data['rm'] ?? $data['nrm'] ?? $data['MedicalRecord'] ?? $data['no_rm'] ?? $rm,
                            'nama'          => $nama,
                            'jenis_kelamin' => $jenisKelamin,
                            'tgl_lahir'     => $tglLahir,
                        ];

                        return response()->json([
                            'success' => true,
                            'source'  => 'api',
                            'pasien'  => $patientData,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::warning("[PasienController] Bed Mon API gagal: " . $e->getMessage());
            }
        }

        // ── Fallback: database lokal ──
        $pasien = Pasien::where('rm', $rm)->first();
        if ($pasien) {
            return response()->json([
                'success' => true,
                'source'  => 'local',
                'pasien'  => [
                    'rm'            => $pasien->rm,
                    'nama'          => $pasien->nama,
                    'jenis_kelamin' => $pasien->jenis_kelamin,
                    'tgl_lahir'     => $pasien->tgl_lahir,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Pasien tidak ditemukan',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\OperationCase;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        // Fetch completed cases that finished submission from all roles
        $cases = OperationCase::where('status', 'Completed')
            ->where('current_flow', 'Selesai')
            ->with(['tindakan', 'operators', 'adminCot', 'va', 'kasir', 'adru'])
            ->get();

        // Map cases to structured schedule details
        $schedules = $cases->map(function ($c) {
            $date = null;
            $time = '-';
            $room = '-';
            $isFinalized = false;

            if ($c->adminCot && $c->adminCot->final_done && $c->adminCot->tanggal_fix) {
                $date = $c->adminCot->tanggal_fix;
                $time = $c->adminCot->jam_fix ?: '-';
                $room = $c->adminCot->kamar_operasi ?: '-';
                $isFinalized = true;
            } else {
                $date = $c->tanggal_pilihan1;
                $time = $c->jam_operasi ?: '-';
                $room = $c->lokasi_tindakan ?: '-';
            }

            // Normalize room name for daily timetable columns
            $normRoom = strtoupper($room);
            $mappedRoom = '1';
            if (strpos($normRoom, '1') !== false) {
                $mappedRoom = '1';
            } elseif (strpos($normRoom, '2') !== false) {
                $mappedRoom = '2';
            } elseif (strpos($normRoom, '3') !== false) {
                $mappedRoom = '3';
            } elseif (strpos($normRoom, '4') !== false) {
                $mappedRoom = '4';
            } elseif (strpos($normRoom, '5') !== false) {
                $mappedRoom = '5';
            } elseif (strpos($normRoom, '6') !== false) {
                $mappedRoom = '6';
            } elseif (strpos($normRoom, 'HYBRID') !== false) {
                $mappedRoom = 'HYBRID';
            } elseif (strpos($normRoom, 'COT LT 5') !== false || strpos($normRoom, 'COT LT. 5') !== false) {
                $mappedRoom = 'COT LT 5';
            } elseif (strpos($normRoom, 'IGD') !== false) {
                $mappedRoom = 'IGD';
            } elseif (strpos($normRoom, 'CATHLAB') !== false || strpos($normRoom, 'CATH') !== false) {
                $mappedRoom = 'CATHLAB';
            } elseif (strpos($normRoom, 'ICU') !== false) {
                $mappedRoom = 'ICU';
            } else {
                $mappedRoom = $room;
            }

            // Join tindakan and operator list
            $tindakanStr = $c->tindakan->pluck('nama')->implode(', ') ?: '-';
            $operatorStr = $c->operators->pluck('nama')->implode(', ') ?: '-';

            // Golongan & Kelas
            $golongan = $c->golongan ?: '-';
            $kelas = $c->kelas_perawatan ?: '-';
            $golonganKelas = "Gol. {$golongan} / {$kelas}";

            // Status Administrasi
            $statusAdmin = '-';
            if ($c->penjamin === 'BPJS Kesehatan') {
                $statusAdmin = 'BPJS Kesehatan (Disetujui VA)';
            } elseif ($c->penjamin === 'Asuransi') {
                $statusAdmin = 'Asuransi ' . ($c->nama_guarantor ? '— ' . $c->nama_guarantor : '') . ' (Disetujui VA)';
            } else {
                $statusAdmin = 'Umum (Disetujui Kasir)';
            }

            // Status Tindakan
            $statusTindakan = $isFinalized ? 'Terjadwal' : 'Selesai Pengajuan';

            // Additional details for the detail panel
            $diagnosis = $c->diagnosis ?: '-';
            $ruangPasca = $c->ruang_pasca_operasi === 'Lainnya' ? $c->ruang_pasca_operasi_lainnya : ($c->ruang_pasca_operasi ?: '-');
            $estimasiRawat = $c->estimasi_rawat_inap ? $c->estimasi_rawat_inap . ' Hari' : '-';
            $estimasiBiaya = 0;
            if ($c->va && $c->va->estimasi_total > 0) {
                $estimasiBiaya = $c->va->estimasi_total;
            } elseif ($c->kasir && $c->kasir->total_estimasi > 0) {
                $estimasiBiaya = $c->kasir->total_estimasi;
            }

            return [
                'id' => $c->id,
                'tanggal_raw' => $date ? $date->format('Y-m-d') : null,
                'tanggal_formatted' => $date ? $date->format('d M Y') : '-',
                'jam' => $time,
                'ruang' => $room,
                'mapped_room' => $mappedRoom,
                'pasien_nama' => $c->nama ?: 'Tanpa Nama',
                'pasien_rm' => $c->rm ?: '-',
                'tindakan' => $tindakanStr,
                'operator' => $operatorStr,
                'golongan_kelas' => $golonganKelas,
                'status_administrasi' => $statusAdmin,
                'status_tindakan' => $statusTindakan,
                'details' => [
                    'diagnosis' => $diagnosis,
                    'ruang_pasca' => $ruangPasca,
                    'estimasi_rawat' => $estimasiRawat,
                    'estimasi_biaya' => 'Rp ' . number_format($estimasiBiaya, 0, ',', '.'),
                    'jenis_operasi' => is_array($c->jenis_operasi) ? implode(', ', $c->jenis_operasi) : ($c->jenis_operasi ?: '-'),
                    'anestesi' => $c->anestesi === 'Lainnya' ? $c->anestesi_lainnya : ($c->anestesi ?: '-'),
                    'estimasi_lama' => $c->estimasi_lama_operasi ?: '-'
                ]
            ];
        });

        return view('schedule.index', compact('schedules'));
    }
}

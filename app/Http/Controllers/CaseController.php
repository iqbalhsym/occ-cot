<?php

namespace App\Http\Controllers;

use App\Models\OperationCase;
use App\Models\CaseDpjp;
use App\Models\CaseOperator;
use App\Models\CaseTindakan;
use App\Models\CaseAlat;
use App\Models\CaseTambahanBmhp;
use App\Models\Pasien;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CaseController extends Controller
{
    // Helper to calculate state logic (matches JavaScript recompute function)
    private function recomputeState(OperationCase $c)
    {
        $penjaminIsAsuransi = ($c->penjamin === 'Asuransi');
        $lokasiIsCot = ($c->lokasi_tindakan === 'COT');

        // Load workflow relations or initialize if missing
        $va = $c->va ?: $c->va()->create([]);
        $kasir = $c->kasir ?: $c->kasir()->create([]);
        $adru = $c->adru ?: $c->adru()->create([]);
        $farmasi = $c->farmasi ?: $c->farmasi()->create([]);
        $adminCot = $c->adminCot ?: $c->adminCot()->create([]);
        $caseManager = $c->caseManager ?: $c->caseManager()->create([]);
        $cs = $c->cs ?: $c->cs()->create([]);

        // required and active flags
        $adminCot->required = $lokasiIsCot;
        $adminCot->save();

        if (in_array($c->status, ['Cancelled', 'Draft'])) {
            return;
        }

        // Evaluate stages completion
        $stage1Done = $penjaminIsAsuransi ? ($va->estimasi_total > 0) : ($kasir->done && $adru->done);
        $cmGateReady = $stage1Done && $farmasi->done && (!$adminCot->required || $adminCot->prelim_done);
        
        $stage2Done = $penjaminIsAsuransi ? ($va->decision === 'Disetujui') : ($kasir->done && $adru->done); // simplified check or status check
        if ($penjaminIsAsuransi) {
            $routeDone = $cs->done;
        } else {
            $routeDone = $stage2Done;
        }
        
        $allDone = $routeDone && (!$adminCot->required || $adminCot->final_done);

        if (!in_array($c->status, ['Returned', 'Cancelled', 'Completed'])) {
            if ($caseManager->done && $allDone) {
                $c->status = 'Completed';
            } else {
                $c->status = 'InProgress';
            }
        }

        // current flow label
        if (!$caseManager->done) {
            if (!$cmGateReady) {
                $c->current_flow = $penjaminIsAsuransi ? 'VA/Farmasi/AdminCOT' : 'Kasir/ADRUCOT/Farmasi/AdminCOT';
            } else {
                $c->current_flow = 'CaseManager';
            }
        } elseif (!$routeDone) {
            $c->current_flow = $penjaminIsAsuransi ? 'VA→CS' : 'Kasir/ADRUCOT';
        } elseif ($adminCot->required && !$adminCot->final_done) {
            $c->current_flow = 'AdminCOT (Final)';
        } else {
            $c->current_flow = 'Selesai';
        }

        $c->save();
    }

    public function index(Request $request)
    {
        // Apply Antrian Saya (My Queue) filter directly on query if requested
        if ($request->query('queue') === 'mine') {
            $activeRole = session('role', 'Nurse');
            if ($activeRole !== 'Viewer') {
                $query = OperationCase::getQueueQueryForRole($activeRole);
            } else {
                $query = OperationCase::whereRaw('1 = 0');
            }
        } else {
            $query = OperationCase::query();
        }

        // Eager load relations
        $query->with(['dpjp', 'tindakan', 'va', 'kasir', 'adru', 'farmasi', 'adminCot', 'caseManager', 'cs']);

        // Quick search
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('id', 'like', "%$s%")
                  ->orWhere('nama', 'like', "%$s%")
                  ->orWhere('rm', 'like', "%$s%");
            });
        }

        // Filter status
        if ($request->filled('status') && $request->status !== 'All') {
            $query->where('status', $request->status);
        }

        // Filter flow
        if ($request->filled('flow') && $request->flow !== 'All') {
            $query->where('current_flow', $request->flow);
        }

        $cases = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('cases.index', compact('cases'));
    }

    public function create()
    {
        if (Auth::user()->role === 'Viewer') {
            abort(403, 'Viewer tidak diperbolehkan membuat kasus baru.');
        }
        return view('cases.create');
    }

    public function store(Request $request)
    {
        if (Auth::user()->role === 'Viewer') {
            return response()->json(['success' => false, 'message' => 'Viewer tidak diperbolehkan membuat kasus baru.'], 403);
        }

        DB::beginTransaction();
        try {
            $caseId = OperationCase::generateId();

            $dob = null;
            if ($request->filled('dobY') && $request->filled('dobM') && $request->filled('dobD')) {
                $dob = $request->dobY . '-' . str_pad($request->dobM, 2, '0', STR_PAD_LEFT) . '-' . str_pad($request->dobD, 2, '0', STR_PAD_LEFT);
            }

            // Create patient if does not exist
            if ($request->filled('rm') && $request->filled('nama')) {
                Pasien::updateOrCreate(
                    ['rm' => $request->rm],
                    [
                        'nama' => $request->nama,
                        'jenis_kelamin' => $request->jenisKelamin ?? 'L',
                        'tgl_lahir' => $dob
                    ]
                );
            }

            $c = OperationCase::create([
                'id' => $caseId,
                'nama' => $request->nama,
                'rm' => $request->rm,
                'jenis_kelamin' => $request->jenisKelamin ?? 'L',
                'tgl_lahir' => $dob,
                'lokasi_pengajuan' => $request->lokasiPengajuan ? [$request->lokasiPengajuan] : [],
                'diagnosis' => $request->diagnosis,
                'jenis_operasi' => $request->jenisOperasi ?? [],
                'anestesi' => $request->anestesi,
                'anestesi_lainnya' => $request->anestesiLainnya,
                'tanggal_pilihan1' => $request->tanggalPilihan1 ?: null,
                'tanggal_pilihan2' => $request->tanggalPilihan2 ?: null,
                'jam_operasi' => $request->jamOperasi ?: null,
                'estimasi_lama_operasi' => $request->estimasiLamaOperasi,
                'lokasi_tindakan' => $request->lokasiTindakan ?? 'COT',
                'lokasi_tindakan_lainnya' => $request->lokasiTindakanLainnya,
                'asal_pasien' => $request->asalPasien,
                'asal_pasien_lainnya' => $request->asalPasienLainnya,
                'ruang_pasca_operasi' => $request->ruangPascaOperasi,
                'ruang_pasca_operasi_lainnya' => $request->ruangPascaOperasiLainnya,
                'estimasi_rawat_inap' => $request->estimasiRawatInap,
                'penjamin' => $request->penjamin ?? 'Umum',
                'nama_guarantor' => $request->namaGuarantor,
                'kelas_perawatan' => $request->kelasPerawatan ?? 'Kelas 3',
                'golongan' => $request->golongan,
                'spesialisasi_op' => $request->spesialisasiOp,
                'status' => 'Draft',
                'current_flow' => 'Nurse'
            ]);

            // Save DPJPs
            if ($request->has('dpjp')) {
                foreach ($request->dpjp as $index => $dpjpName) {
                    if (!empty($dpjpName)) {
                        $c->dpjp()->create(['nama' => $dpjpName, 'urutan' => $index]);
                    }
                }
            }

            // Save Operators
            if ($request->has('operator')) {
                foreach ($request->operator as $index => $opName) {
                    if (!empty($opName)) {
                        $c->operators()->create([
                            'nama' => $opName,
                            'spesialisasi' => $request->operatorSpesialisasi[$index] ?? null,
                            'urutan' => $index
                        ]);
                    }
                }
            }

            // Save Tindakan
            if ($request->has('tindakan')) {
                foreach ($request->tindakan as $index => $tName) {
                    if (!empty($tName)) {
                        $c->tindakan()->create(['nama' => $tName, 'urutan' => $index]);
                    }
                }
            }

            // Save Alat Khusus
            if ($request->has('alat')) {
                foreach ($request->alat as $aName) {
                    if (!empty($aName)) {
                        $c->alat()->create(['nama' => $aName]);
                    }
                }
            }

            // Save Tambahan BMHP
            if ($request->has('tambahanBmhpNama')) {
                foreach ($request->tambahanBmhpNama as $index => $tbName) {
                    if (!empty($tbName)) {
                        $c->tambahanBmhp()->create([
                            'nama' => $tbName,
                            'qty' => $request->tambahanBmhpQty[$index] ?? 1
                        ]);
                    }
                }
            }

            // Initialize all workflow tables
            $c->va()->create([]);
            $c->kasir()->create([]);
            $c->adru()->create([]);
            $c->farmasi()->create([]);
            $c->adminCot()->create(['required' => ($c->lokasi_tindakan === 'COT')]);
            $c->caseManager()->create([]);
            $c->cs()->create([]);

            $c->addAudit("Case dibuat (Draft)", "Data diinput Nurse berdasarkan Form Penjadwalan Tindakan.", "Nurse");

            DB::commit();
            return response()->json(['success' => true, 'id' => $c->id, 'message' => 'Draft berhasil disimpan']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan draft: ' . $e->getMessage()]);
        }
    }

    public function show($id)
    {
        $case = OperationCase::with(['dpjp', 'operators', 'tindakan', 'alat', 'tambahanBmhp', 'audit', 'va', 'kasir', 'adru', 'farmasi', 'adminCot', 'caseManager', 'cs'])->findOrFail($id);
        return view('cases.show', compact('case'));
    }

    public function edit($id)
    {
        if (Auth::user()->role === 'Viewer') {
            abort(403, 'Viewer tidak diperbolehkan mengedit kasus.');
        }
        $case = OperationCase::with(['dpjp', 'operators', 'tindakan', 'alat', 'tambahanBmhp'])->findOrFail($id);
        return view('cases.edit', compact('case'));
    }

    public function update(Request $request, $id)
    {
        if (Auth::user()->role === 'Viewer') {
            return response()->json(['success' => false, 'message' => 'Viewer tidak diperbolehkan memperbarui kasus.'], 403);
        }
        $c = OperationCase::findOrFail($id);

        DB::beginTransaction();
        try {
            $dob = null;
            if ($request->filled('dobY') && $request->filled('dobM') && $request->filled('dobD')) {
                $dob = $request->dobY . '-' . str_pad($request->dobM, 2, '0', STR_PAD_LEFT) . '-' . str_pad($request->dobD, 2, '0', STR_PAD_LEFT);
            }

            $c->update([
                'nama' => $request->nama,
                'jenis_kelamin' => $request->jenisKelamin ?? 'L',
                'tgl_lahir' => $dob,
                'lokasi_pengajuan' => $request->lokasiPengajuan ? [$request->lokasiPengajuan] : [],
                'diagnosis' => $request->diagnosis,
                'jenis_operasi' => $request->jenisOperasi ?? [],
                'anestesi' => $request->anestesi,
                'anestesi_lainnya' => $request->anestesiLainnya,
                'tanggal_pilihan1' => $request->tanggalPilihan1 ?: null,
                'tanggal_pilihan2' => $request->tanggalPilihan2 ?: null,
                'jam_operasi' => $request->jamOperasi ?: null,
                'estimasi_lama_operasi' => $request->estimasi_lama_operasi,
                'lokasi_tindakan' => $request->lokasiTindakan ?? 'COT',
                'lokasi_tindakan_lainnya' => $request->lokasiTindakanLainnya,
                'asal_pasien' => $request->asalPasien,
                'asal_pasien_lainnya' => $request->asalPasienLainnya,
                'ruang_pasca_operasi' => $request->ruangPascaOperasi,
                'ruang_pasca_operasi_lainnya' => $request->ruangPascaOperasiLainnya,
                'estimasi_rawat_inap' => $request->estimasiRawatInap,
                'penjamin' => $request->penjamin ?? 'Umum',
                'nama_guarantor' => $request->namaGuarantor,
                'kelas_perawatan' => $request->kelasPerawatan ?? 'Kelas 3',
                'golongan' => $request->golongan,
                'spesialisasi_op' => $request->spesialisasiOp,
            ]);

            // Sync related lists
            $c->dpjp()->delete();
            if ($request->has('dpjp')) {
                foreach ($request->dpjp as $index => $dpjpName) {
                    if (!empty($dpjpName)) {
                        $c->dpjp()->create(['nama' => $dpjpName, 'urutan' => $index]);
                    }
                }
            }

            $c->operators()->delete();
            if ($request->has('operator')) {
                foreach ($request->operator as $index => $opName) {
                    if (!empty($opName)) {
                        $c->operators()->create([
                            'nama' => $opName,
                            'spesialisasi' => $request->operatorSpesialisasi[$index] ?? null,
                            'urutan' => $index
                        ]);
                    }
                }
            }

            $c->tindakan()->delete();
            if ($request->has('tindakan')) {
                foreach ($request->tindakan as $index => $tName) {
                    if (!empty($tName)) {
                        $c->tindakan()->create(['nama' => $tName, 'urutan' => $index]);
                    }
                }
            }

            $c->alat()->delete();
            if ($request->has('alat')) {
                foreach ($request->alat as $aName) {
                    if (!empty($aName)) {
                        $c->alat()->create(['nama' => $aName]);
                    }
                }
            }

            $c->tambahanBmhp()->delete();
            if ($request->has('tambahanBmhpNama')) {
                foreach ($request->tambahanBmhpNama as $index => $tbName) {
                    if (!empty($tbName)) {
                        $c->tambahanBmhp()->create([
                            'nama' => $tbName,
                            'qty' => $request->tambahanBmhpQty[$index] ?? 1
                        ]);
                    }
                }
            }

            $auditNote = $c->status === 'Returned' ? 'Nurse memperbaiki data sesuai catatan Returned.' : 'Perubahan data draft.';
            $c->addAudit("Data diedit", $auditNote, "Nurse");

            if ($c->status === 'Returned') {
                $c->status = 'Draft';
            }
            
            $this->recomputeState($c);

            DB::commit();
            return response()->json(['success' => true, 'id' => $c->id, 'message' => 'Draft berhasil diperbarui']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui draft: ' . $e->getMessage()]);
        }
    }

    public function submit($id)
    {
        $c = OperationCase::findOrFail($id);
        $c->status = 'Submitted';
        $c->save();

        $c->addAudit("Submit pengajuan", "Case ID dibuat, sistem broadcast ke unit terkait.", "Nurse");

        // Determine units based on penjamin and lokasi
        $units = [];
        if ($c->penjamin === 'Asuransi') {
            $units[] = 'VA';
        } else {
            $units[] = 'Kasir';
            $units[] = 'ADRU COT';
        }
        $units[] = 'Farmasi';
        if ($c->lokasi_tindakan === 'COT') {
            $units[] = 'Admin COT';
        }

        $c->addAudit("Broadcast Workflow Engine", "Diteruskan ke: " . implode(', ', $units) . '.', "Sistem");

        $this->recomputeState($c);

        return response()->json(['success' => true, 'message' => 'Kasus berhasil diajukan']);
    }

    public function cancel(Request $request, $id)
    {
        $c = OperationCase::findOrFail($id);
        $c->status = 'Cancelled';
        $c->save();

        $c->addAudit("Case dibatalkan", $request->note ?: 'Kasus dibatalkan oleh Nurse.', 'Nurse');
        $this->recomputeState($c);

        return response()->json(['success' => true, 'message' => 'Kasus berhasil dibatalkan']);
    }

    // Individual role action methods (reflecting role action JS methods)
    public function vaAction(Request $request, $id)
    {
        $c = OperationCase::with(['va'])->findOrFail($id);
        $va = $c->va;
        $action = $request->action;

        if ($action === 'ajukan1') {
            $va->kelas = $request->kelas ?: $c->kelas_perawatan;
            $va->golongan = $request->golongan ?: $c->golongan;
            $va->estimasi_rincian = $request->rincian ?: [];
            $va->estimasi_total = $request->total ?: 0;
            $va->done = false; // goes to Case Manager first
            $va->save();

            $c->addAudit(
                "VA mengajukan estimasi biaya ke Case Manager",
                "Golongan {$va->golongan} / {$va->kelas} — Total Rp " . number_format($va->estimasi_total, 0, ',', '.') . ". " . ($request->note ?: ''),
                "VA"
            );
        } elseif ($action === 'revisi1') {
            $c->status = 'Returned';
            $c->save();
            $c->addAudit("VA meminta revisi data ke Nurse", $request->note ?: '', "VA");
        } elseif ($action === 'mulai') {
            $va->decision = 'DalamKonfirmasi';
            $va->decision_note = $request->note ?: 'Pengajuan estimasi dikirim ke pihak asuransi.';
            $va->save();
            $c->addAudit("VA mulai verifikasi — pengajuan estimasi ke asuransi", $va->decision_note, "VA");
        } elseif ($action === 'berkasBelumLengkap') {
            $va->decision = 'DalamKonfirmasi';
            $va->decision_note = $request->note ?: 'Menunggu kelengkapan berkas.';
            $va->save();
            $c->addAudit("VA menandai berkas belum lengkap", $request->note ?: '', "VA");
        } elseif ($action === 'berkasLengkap') {
            $va->decision_note = $request->note ?: 'Berkas dilengkapi.';
            $va->save();
            $c->addAudit("Berkas dilengkapi — kembali ke VA untuk diproses", $request->note ?: '', "Nurse");
        } elseif ($action === 'disetujui') {
            $va->decision = 'Disetujui';
            $va->decision_note = $request->note ?: '';
            $va->done = true;
            $va->save();
            $c->addAudit(
                "Asuransi menyetujui (Disetujui) — diteruskan ke Customer Service",
                ($request->note ?: '') . " | Notifikasi: Case Manager (in-app) & Operator (Telegram).",
                "VA"
            );
        } elseif (in_array($action, ['pending', 'ditolak', 'dalamKonfirmasi'])) {
            $va->decision = ($action === 'pending' ? 'Pending' : ($action === 'ditolak' ? 'Ditolak' : 'DalamKonfirmasi'));
            $va->decision_note = $request->note ?: '';
            $va->save();
            $c->addAudit("VA: Status Asuransi updated - " . $va->decision, $va->decision_note, "VA");
        }

        $this->recomputeState($c);
        return response()->json(['success' => true, 'message' => 'Aksi VA berhasil diproses']);
    }

    public function kasirAction(Request $request, $id)
    {
        $c = OperationCase::with(['kasir'])->findOrFail($id);
        $kasir = $c->kasir;
        $action = $request->action;

        if ($action === 'mulai') {
            $c->addAudit("Kasir mulai proses administrasi pasien umum", "", "Kasir");
        } elseif ($action === 'selesai1') {
            $kasir->done = true; // stage 1
            $kasir->note = $request->note ?: '';
            $kasir->save();
            $c->addAudit("Kasir menyelesaikan administrasi tahap awal", $kasir->note, "Kasir");
        } elseif ($action === 'revisi1') {
            $kasir->done = false;
            $kasir->save();
            $c->status = 'Returned';
            $c->save();
            $c->addAudit("Kasir meminta revisi ke Nurse", $request->note ?: '', "Kasir");
        } elseif ($action === 'selesai2') {
            $kasir->done = true; // stage 2
            $kasir->note = $request->note ?: '';
            $kasir->save();
            $c->addAudit("Kasir menyelesaikan administrasi akhir (pasca approval)", $kasir->note, "Kasir");
        }

        $this->recomputeState($c);
        return response()->json(['success' => true, 'message' => 'Aksi Kasir berhasil diproses']);
    }

    public function adruAction(Request $request, $id)
    {
        $c = OperationCase::with(['adru'])->findOrFail($id);
        $adru = $c->adru;
        $action = $request->action;

        if ($action === 'mulai') {
            $c->addAudit("ADRU COT mulai menghitung estimasi biaya", "", "ADRU");
        } elseif ($action === 'ajukan1') {
            $adru->done = true; // stage1 done
            $adru->estimasi = $request->estimasi ?: '';
            $adru->note = $request->note ?: '';
            $adru->save();
            $c->addAudit(
                "ADRU COT mengajukan estimasi ke Case Manager",
                "Estimasi: {$adru->estimasi}. " . ($request->note ?: ''),
                "ADRU"
            );
        } elseif ($action === 'revisi1') {
            $adru->done = false;
            $adru->save();
            $c->status = 'Returned';
            $c->save();
            $c->addAudit("ADRU COT meminta revisi ke Nurse", $request->note ?: '', "ADRU");
        } elseif ($action === 'konfirmasi2') {
            $adru->done = true; // stage 2 done
            $adru->note = $request->note ?: '';
            $adru->save();
            $c->addAudit(
                "ADRU COT: pasien setuju, diteruskan langsung ke Admin COT (tanpa CS)",
                $request->note ?: '',
                "ADRU"
            );
        }

        $this->recomputeState($c);
        return response()->json(['success' => true, 'message' => 'Aksi ADRU berhasil diproses']);
    }

    public function farmasiAction(Request $request, $id)
    {
        $c = OperationCase::with(['farmasi'])->findOrFail($id);
        $farmasi = $c->farmasi;
        $action = $request->action;

        if ($action === 'mulai') {
            $c->addAudit("Farmasi mulai review BMHP/obat", "", "Farmasi");
        } elseif ($action === 'setuju') {
            $farmasi->done = true;
            $farmasi->note = $request->note ?: '';
            $farmasi->save();
            $c->addAudit("Farmasi menyetujui paket BMHP/obat", $farmasi->note, "Farmasi");
        } elseif ($action === 'revisi') {
            $farmasi->done = false;
            $farmasi->save();
            $c->status = 'Returned';
            $c->save();
            $c->addAudit("Farmasi meminta revisi paket BMHP", $request->note ?: '', "Farmasi");
        }

        $this->recomputeState($c);
        return response()->json(['success' => true, 'message' => 'Aksi Farmasi berhasil diproses']);
    }

    public function adminCotAction(Request $request, $id)
    {
        $c = OperationCase::with(['adminCot'])->findOrFail($id);
        $adminCot = $c->adminCot;
        $action = $request->action;

        if ($action === 'prelim') {
            $adminCot->prelim_done = true;
            $adminCot->save();

            // Save new instruments if provided
            if ($request->has('alat')) {
                $c->alat()->delete();
                foreach ($request->alat as $aName) {
                    if (!empty($aName)) {
                        $c->alat()->create(['nama' => $aName]);
                    }
                }
            }

            $c->addAudit(
                "Admin COT menentukan kebutuhan alat (awal)",
                "Alat: " . implode(', ', $request->alat ?? []),
                "Admin COT"
            );
        } elseif ($action === 'final') {
            $adminCot->final_done = true;
            $adminCot->decision = 'Terjadwal';
            $adminCot->tanggal_fix = $request->tanggal;
            $adminCot->jam_fix = $request->jam;
            $adminCot->kamar_operasi = $request->ruang;
            $adminCot->save();

            $editedText = $request->edited ? " (diubah dari permintaan awal: {$request->note})" : " (sesuai permintaan awal)";
            $c->addAudit(
                "Admin COT menetapkan jadwal final operasi",
                "{$request->tanggal} {$request->jam} @ {$request->ruang}{$editedText}",
                "Admin COT"
            );
        } elseif ($action === 'dalamKonfirmasi') {
            $adminCot->decision = 'DalamKonfirmasi';
            $adminCot->decision_note = $request->note ?: '';
            $adminCot->save();
            $c->addAudit("Admin COT: Dalam Konfirmasi ke operator/unit terkait", $request->note ?: '', "Admin COT");
        } elseif ($action === 'revisi') {
            $adminCot->decision = 'Revisi';
            $adminCot->decision_note = $request->note ?: '';
            $adminCot->save();

            $target = $request->returnTo ?: ($c->penjamin === 'Asuransi' ? 'VA' : 'Kasir');
            $c->caseManager->done = false;
            $c->caseManager->save();

            if ($target === 'VA') {
                $c->va->estimasi_total = 0; // resets stage 1
                $c->va->save();
            } elseif ($target === 'Kasir') {
                $c->kasir->done = false;
                $c->kasir->save();
            } elseif ($target === 'ADRUCOT') {
                $c->adru->done = false;
                $c->adru->save();
            }

            $c->addAudit("Admin COT meminta revisi estimasi (tambahan alat/perubahan golongan) → " . $target, $request->note ?: '', "Admin COT");
        } elseif ($action === 'reschedule') {
            $adminCot->decision = 'Reschedule';
            $adminCot->decision_note = $request->note ?: '';
            $adminCot->tanggal_fix = $request->tanggal;
            $adminCot->jam_fix = $request->jam;
            $adminCot->kamar_operasi = $request->ruang;
            $adminCot->save();

            $c->addAudit(
                "Admin COT melakukan reschedule jadwal",
                "Reschedule ke {$request->tanggal} {$request->jam} @ {$request->ruang}. " . ($request->note ?: ''),
                "Admin COT"
            );
        }

        $this->recomputeState($c);
        return response()->json(['success' => true, 'message' => 'Aksi Admin COT berhasil diproses']);
    }

    public function caseManagerAction(Request $request, $id)
    {
        $c = OperationCase::with(['caseManager'])->findOrFail($id);
        $cm = $c->caseManager;
        $action = $request->action;

        if ($action === 'setuju') {
            $cm->done = true;
            $cm->decision = 'Disetujui';
            $cm->instruksi = $request->note ?: '';
            $cm->save();

            $c->addAudit(
                "Case Manager menyetujui estimasi & dokumen (LMA/CL lengkap)",
                $request->note ?: '',
                "Case Manager"
            );
        } elseif ($action === 'revisi') {
            $cm->done = false;
            $cm->decision = 'Revisi';
            $cm->return_to = $request->returnTo ?: 'Nurse';
            $cm->instruksi = $request->note ?: '';
            $cm->save();

            $target = $cm->return_to;
            if ($target === 'Nurse') {
                $c->status = 'Returned';
                $c->save();
            } elseif ($target === 'VA') {
                $c->va->estimasi_total = 0;
                $c->va->save();
            } elseif ($target === 'Kasir') {
                $c->kasir->done = false;
                $c->kasir->save();
            } elseif ($target === 'ADRUCOT') {
                $c->adru->done = false;
                $c->adru->save();
            } elseif ($target === 'Farmasi') {
                $c->farmasi->done = false;
                $c->farmasi->save();
            } elseif ($target === 'AdminCOT') {
                $c->adminCot->prelim_done = false;
                $c->adminCot->save();
            }

            $c->addAudit(
                "Case Manager mengembalikan berkas untuk revisi → " . $target,
                $request->note ?: '',
                "Case Manager"
            );
        } elseif ($action === 'dokbelumlengkap') {
            $cm->decision = 'DokumenBelumLengkap';
            $cm->instruksi = $request->note ?: '';
            $cm->save();

            $c->addAudit(
                "Case Manager: dokumen asuransi belum lengkap",
                $request->note ?: '',
                "Case Manager"
            );
        }

        $this->recomputeState($c);
        return response()->json(['success' => true, 'message' => 'Aksi Case Manager berhasil diproses']);
    }

    public function csAction(Request $request, $id)
    {
        $c = OperationCase::with(['cs'])->findOrFail($id);
        $cs = $c->cs;
        $action = $request->action;

        if ($action === 'hubungi') {
            $cs->decision = 'DalamKonfirmasi';
            $cs->decision_note = $request->note ?: 'Menunggu respon pasien.';
            $cs->save();
            $c->addAudit("CS menghubungi pasien — menunggu respon", $cs->decision_note, "CS");
        } elseif ($action === 'disetujui') {
            $cs->done = true;
            $cs->decision = 'Disetujui';
            $cs->decision_note = $request->note ?: '';
            $cs->save();

            $c->addAudit("CS: pasien setuju tindakan — diteruskan ke Admin COT untuk penjadwalan", $request->note ?: '', "CS");
        } elseif ($action === 'reschedule') {
            $cs->decision = 'Reschedule';
            $cs->decision_note = $request->note ?: '';
            $cs->save();

            $c->addAudit("CS: pasien acc namun minta reschedule tanggal lain", "Catatan: " . ($request->note ?: ''), "CS");
        } elseif ($action === 'dalamKonfirmasi') {
            $cs->decision = 'DalamKonfirmasi';
            $cs->decision_note = $request->note ?: '';
            $cs->save();

            $c->caseManager->done = false;
            $c->caseManager->decision = 'DalamKonfirmasi';
            $c->caseManager->instruksi = "Keberatan pasien (dari CS): " . ($request->note ?: '');
            $c->caseManager->save();

            $c->addAudit("CS: keberatan pasien — berkas dikembalikan ke Case Manager", $request->note ?: '', "CS");
        } elseif ($action === 'batal') {
            $cs->decision = 'Batal';
            $cs->decision_note = $request->note ?: '';
            $cs->save();

            $c->status = 'Cancelled';
            $c->save();

            $c->addAudit("CS: pasien membatalkan — proses dihentikan", $request->note ?: '', "CS");
        }

        $this->recomputeState($c);
        return response()->json(['success' => true, 'message' => 'Aksi CS berhasil diproses']);
    }

    public function downloadEstimasi($id)
    {
        $c = OperationCase::with(['va', 'tindakan'])->findOrFail($id);
        
        // Return a print window HTML document identical to buildEstimasiDoc in template
        $rincian = ($c->va && $c->va->estimasi_rincian) ? $c->va->estimasi_rincian : [];
        $total = $c->va ? $c->va->estimasi_total : 0;
        
        $dpjpNames = $c->dpjp->pluck('nama')->implode(', ') ?: '-';
        $tindakanNames = $c->tindakan->pluck('nama')->implode(', ') ?: '-';
        
        $rowsHtml = '';
        foreach ($rincian as $index => $r) {
            $num = $index + 1;
            $komponen = htmlspecialchars($r['komponen'] ?? '');
            $nilai = number_format($r['nilai'] ?? 0, 0, ',', '.');
            $rowsHtml .= "<tr><td style=\"width:28px;\">{$num}</td><td>{$komponen}</td><td style=\"text-align:right;\">{$nilai}</td></tr>";
        }
        if (empty($rincian)) {
            $rowsHtml = '<tr><td colspan="3" style="text-align:center;color:#888;">Belum ada rincian estimasi.</td></tr>';
        }
        
        $cId = htmlspecialchars($c->id);
        $namaPasien = htmlspecialchars($c->nama);
        $rmPasien = htmlspecialchars($c->rm);
        $tglLahir = htmlspecialchars($c->tgl_lahir ?: '-');
        $diagnosis = htmlspecialchars($c->diagnosis ?: '-');
        $kelas = htmlspecialchars(($c->va && $c->va->kelas) ? $c->va->kelas : ($c->kelas_perawatan ?: '-'));
        $penjamin = htmlspecialchars($c->penjamin . ($c->nama_guarantor ? ' — ' . $c->nama_guarantor : ''));
        $golongan = htmlspecialchars(($c->va && $c->va->golongan) ? $c->va->golongan : ($c->golongan ?: '-'));
        $tglSekarang = now()->locale('id')->isoFormat('D MMMM Y');
        $totalRupiah = number_format($total, 0, ',', '.');

        $htmlContent = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Estimasi {$cId}</title>
<style>
  body{font-family:"Times New Roman",serif;font-size:13px;color:#000;max-width:720px;margin:24px auto;padding:0 24px;}
  h2{text-align:center;text-transform:uppercase;letter-spacing:1px;margin:4px 0 18px;font-size:16px;}
  table{width:100%;border-collapse:collapse;}
  .id-tbl td{padding:2px 4px;vertical-align:top;}
  .id-tbl td.k{width:170px;} .id-tbl td.s{width:14px;}
  .biaya th,.biaya td{border:1px solid #333;padding:5px 8px;}
  .biaya th{background:#eee;text-align:left;}
  .total td{font-weight:bold;border:1px solid #333;padding:6px 8px;}
  .note{font-size:12px;margin-top:14px;}
  .sign{margin-top:36px;display:flex;justify-content:space-between;text-align:center;font-size:12px;}
  .code{margin-top:30px;font-size:10px;color:#555;}
  @media print{ .noprint{display:none;} body{margin:0;} }
</style>
</head>
<body>
  <div class="noprint" style="text-align:right;margin-bottom:8px;"><button onclick="window.print()">🖨️ Cetak / Simpan PDF</button></div>
  <div style="font-weight:bold;">RUMAH SAKIT UNIVERSITAS INDONESIA</div>
  <div style="font-size:11px;margin-bottom:6px;">Central Operating Theatre (COT)</div>
  <hr>
  <h2>Perkiraan Biaya Tindakan</h2>
  <div style="font-weight:bold;margin-bottom:4px;">Identitas Pasien</div>
  <table class="id-tbl">
    <tr><td class="k">Nama Pasien</td><td class="s">:</td><td>{$namaPasien}</td></tr>
    <tr><td class="k">No. Rekam Medis</td><td class="s">:</td><td>{$rmPasien}</td></tr>
    <tr><td class="k">Tanggal Lahir</td><td class="s">:</td><td>{$tglLahir}</td></tr>
    <tr><td class="k">Diagnosa</td><td class="s">:</td><td>{$diagnosis}</td></tr>
    <tr><td class="k">Operator</td><td class="s">:</td><td>{$dpjpNames}</td></tr>
    <tr><td class="k">Tindakan</td><td class="s">:</td><td>{$tindakanNames}</td></tr>
    <tr><td class="k">Kelas Perawatan</td><td class="s">:</td><td>{$kelas}</td></tr>
    <tr><td class="k">Jaminan</td><td class="s">:</td><td>{$penjamin}</td></tr>
    <tr><td class="k">Golongan Tindakan</td><td class="s">:</td><td>{$golongan}</td></tr>
  </table>
  <div style="font-weight:bold;margin:18px 0 6px;">Rincian Estimasi Biaya</div>
  <table class="biaya">
    <thead>
      <tr><th style="width:28px;">No</th><th>Komponen Biaya</th><th style="text-align:right;width:150px;">Nilai (Rp)</th></tr>
    </thead>
    <tbody>
      {$rowsHtml}
      <tr class="total">
        <td colspan="2" style="text-align:right;">TOTAL PERKIRAAN BIAYA</td>
        <td style="text-align:right;">{$totalRupiah}</td>
      </tr>
    </tbody>
  </table>
  <div class="note">
    <strong>Catatan penting:</strong><br>
    1. Nilai di atas adalah <strong>estimasi awal</strong>. Biaya riil mengikuti tindakan & BMHP aktual saat operasi.<br>
    2. Estimasi ini belum termasuk obat pulang & sewa kamar perawatan pasca bedah.
  </div>
  <div class="sign">
    <div></div>
    <div>
      Depok, {$tglSekarang}<br>Petugas Administrasi COT RSUI
      <br><br><br><br>
      ( ____________________ )
    </div>
  </div>
  <div class="code">No. Dokumen: FOR-09/ADMISI/RSUI · Case ID: {$cId}</div>
</body>
</html>
HTML;
        return response($htmlContent);
    }
}

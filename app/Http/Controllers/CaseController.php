<?php

namespace App\Http\Controllers;

use App\Models\OperationCase;
use App\Models\CaseDpjp;
use App\Models\CaseOperator;
use App\Models\CaseTindakan;
use App\Models\CaseAlat;
use App\Models\CaseTambahanBmhp;
use App\Models\Pasien;
use App\Models\GuarantorMapping;
use App\Models\EstimasiHistory;
use App\Models\RolePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CaseController extends Controller
{
    // Helper to calculate state logic (matches JavaScript recompute function)
    private function recomputeState(OperationCase $c)
    {
        // Load workflow relations or initialize if missing
        $va = $c->va ?: $c->va()->firstOrCreate([]);
        $kasir = $c->kasir ?: $c->kasir()->firstOrCreate([]);
        $adru = $c->adru ?: $c->adru()->firstOrCreate([]);
        $farmasi = $c->farmasi ?: $c->farmasi()->firstOrCreate([]);
        $adminCot = $c->adminCot ?: $c->adminCot()->firstOrCreate([]);
        $caseManager = $c->caseManager ?: $c->caseManager()->firstOrCreate([]);
        $cs = $c->cs ?: $c->cs()->firstOrCreate([]);

        // required and active flags
        $adminCot->required = true;
        $adminCot->save();

        if (in_array($c->status, ['Cancelled', 'Draft'])) {
            return;
        }

        // Parallel stage completion evaluations
        $activeUnits = [];
        if (!$farmasi->done) {
            $activeUnits[] = 'Farmasi';
        }
        if (!$adminCot->final_done) {
            $activeUnits[] = 'AdminCOT';
        }
        if (!$caseManager->done) {
            $activeUnits[] = 'CaseManager';
        }

        $allDone = empty($activeUnits);
        $isRejected = ($caseManager->decision === 'Ditolak');

        if (!in_array($c->status, ['Returned', 'Cancelled', 'Completed'])) {
            if ($isRejected || $allDone) {
                $c->status = 'Completed';
            } else {
                $c->status = 'InProgress';
            }
        }

        if ($c->status === 'Completed') {
            $c->current_flow = 'Selesai';
        } elseif ($c->status === 'Cancelled') {
            $c->current_flow = 'Batal';
        } else {
            $c->current_flow = implode('/', $activeUnits) ?: 'Selesai';
        }

        $c->save();
    }

    public function index(Request $request)
    {
        $currentUser = Auth::user();
        $activeRole = session('role', $currentUser ? $currentUser->role : 'Viewer');
        
        if ($request->query('queue') === 'mine') {
            $query = OperationCase::getQueueQueryForRole($activeRole);
        } else {
            $query = OperationCase::query();
        }

        $query->with(['dpjp', 'tindakan', 'operators', 'alat', 'tambahanBmhp']);

        // Penjamin filter
        if ($request->filled('penjamin') && $request->query('penjamin') !== 'All') {
            $query->where('penjamin', $request->query('penjamin'));
        }

        // Lokasi filter
        if ($request->filled('lokasi') && $request->query('lokasi') !== 'All') {
            $query->where('lokasi_tindakan', $request->query('lokasi'));
        }

        // Modul Aktif (current_flow) filter
        if ($request->filled('flow') && $request->query('flow') !== 'All') {
            $query->where('current_flow', $request->query('flow'));
        }

        // Status filter
        if ($request->filled('status') && $request->query('status') !== 'All') {
            $query->where('status', $request->query('status'));
        } else {
            // By default, if not Nurse, hide Drafts
            if ($activeRole !== 'Nurse') {
                $query->where('status', '!=', 'Draft');
            }
        }

        // Search text
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('nama', 'like', "%{$search}%")
                  ->orWhere('rm', 'like', "%{$search}%");
            });
        }

        $cases = $query->orderBy('created_at', 'desc')->get();

        return view('cases.index', compact('cases'));
    }

    public function create()
    {
        return view('cases.create');
    }

    public function store(Request $request)
    {
        $realRole = Auth::user()->role;
        if (!in_array($realRole, ['Nurse', 'SuperAdmin', 'Administrator'])) {
            return response()->json(['success' => false, 'message' => 'Hanya Nurse, SuperAdmin, atau Administrator yang diperbolehkan membuat kasus baru.'], 403);
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
                'estimasi_lama_operasi' => (($request->filled('estimasiLamaOperasiJam') || $request->filled('estimasiLamaOperasiMenit')) ? ((int)$request->estimasiLamaOperasiJam . " Jam " . (int)$request->estimasiLamaOperasiMenit . " Menit") : null),
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
                        $masterAlat = \App\Models\AlatKhusus::where('nama', $aName)->first();
                        $c->alat()->create([
                            'nama' => $aName,
                            'harga' => $masterAlat ? $masterAlat->tarif : 0
                        ]);
                    }
                }
            }

            // Save Tambahan BMHP
            if ($request->has('tambahanBmhpNama')) {
                foreach ($request->tambahanBmhpNama as $index => $tbName) {
                    if (!empty($tbName)) {
                        $masterBmhp = \App\Models\PaketBmhp::where('nama', $tbName)->first();
                        $c->tambahanBmhp()->create([
                            'nama' => $tbName,
                            'qty' => $request->tambahanBmhpQty[$index] ?? 1,
                            'harga' => $masterBmhp ? $masterBmhp->tarif : 0,
                            'jenis' => 'tambahan'
                        ]);
                    }
                }
            }

            // Initialize all workflow tables
            $c->va()->firstOrCreate([]);
            $c->kasir()->firstOrCreate([]);
            $c->adru()->firstOrCreate([]);
            $c->farmasi()->firstOrCreate([]);
            $c->adminCot()->firstOrCreate(['required' => true]);
            $c->caseManager()->firstOrCreate([]);
            $c->cs()->firstOrCreate([]);

            // Build raw data
            $payload = $this->buildRawDataFromRequest($request, $caseId);
            $c->raw_data = $payload['raw_data'];
            $c->expensive_flag = $payload['expensive_flag'];
            $c->save();

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
        
        $activeRole = session('role', Auth::user()->role);
        if ($activeRole !== 'Nurse' && $case->status === 'Draft') {
            abort(403, 'Akses ditolak. Pengajuan masih berstatus Draft.');
        }

        $mappings = \App\Models\GuarantorMapping::all();
        $db = $this->getParsedDatabase();
        $masterTarifDb = $db['masterTarifDb'] ?: [];

        return view('cases.show', compact('case', 'mappings', 'masterTarifDb'));
    }

    public function edit($id)
    {
        $case = OperationCase::with(['dpjp', 'operators', 'tindakan', 'alat', 'tambahanBmhp'])->findOrFail($id);
        
        $activeRole = session('role', Auth::user()->role);
        if ($activeRole !== 'Nurse' && $case->status === 'Draft') {
            abort(403, 'Akses ditolak. Pengajuan masih berstatus Draft.');
        }
        
        if ($activeRole === 'Nurse' && !in_array($case->status, ['Draft', 'Returned'])) {
            abort(403, 'Nurse tidak diperbolehkan mengedit kasus yang sudah dikirim/diajukan.');
        }

        return view('cases.edit', compact('case'));
    }

    public function update(Request $request, $id)
    {
        if (Auth::user()->role === 'Viewer') {
            return response()->json(['success' => false, 'message' => 'Viewer tidak diperbolehkan memperbarui kasus.'], 403);
        }
        $c = OperationCase::findOrFail($id);

        $activeRole = session('role', Auth::user()->role);
        if ($activeRole !== 'Nurse' && $c->status === 'Draft') {
            return response()->json(['success' => false, 'message' => 'Akses ditolak. Pengajuan masih berstatus Draft.'], 403);
        }

        if ($activeRole === 'Nurse' && !in_array($c->status, ['Draft', 'Returned'])) {
            return response()->json(['success' => false, 'message' => 'Nurse tidak diperbolehkan memperbarui kasus yang sudah dikirim/diajukan.'], 403);
        }

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
                'estimasi_lama_operasi' => (($request->filled('estimasiLamaOperasiJam') || $request->filled('estimasiLamaOperasiMenit')) ? ((int)$request->estimasiLamaOperasiJam . " Jam " . (int)$request->estimasiLamaOperasiMenit . " Menit") : null),
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
                        $masterAlat = \App\Models\AlatKhusus::where('nama', $aName)->first();
                        $c->alat()->create([
                            'nama' => $aName,
                            'harga' => $masterAlat ? $masterAlat->tarif : 0
                        ]);
                    }
                }
            }

            $c->tambahanBmhp()->delete();
            if ($request->has('tambahanBmhpNama')) {
                foreach ($request->tambahanBmhpNama as $index => $tbName) {
                    if (!empty($tbName)) {
                        $masterBmhp = \App\Models\PaketBmhp::where('nama', $tbName)->first();
                        $c->tambahanBmhp()->create([
                            'nama' => $tbName,
                            'qty' => $request->tambahanBmhpQty[$index] ?? 1,
                            'harga' => $masterBmhp ? $masterBmhp->tarif : 0,
                            'jenis' => 'tambahan'
                        ]);
                    }
                }
            }

            // Build raw data
            $payload = $this->buildRawDataFromRequest($request, $c->id, $c->raw_data);
            $c->raw_data = $payload['raw_data'];
            $c->expensive_flag = $payload['expensive_flag'];
            $c->save();

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

        $rawData = json_decode($c->raw_data, true) ?: [];
        $attachments = $rawData['attachments'] ?? [];
        if (count($attachments) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Minimal 1 dokumen (Formulir Penjadwalan Tindakan) wajib diunggah sebelum Submit Pengajuan.'
            ], 422);
        }

        $c->status = 'Submitted';
        $c->save();

        $c->addAudit("Submit pengajuan", "Case ID dibuat, sistem broadcast ke unit terkait.", "Nurse");

        // Populate default BMHP package items from seeder database
        $htmlPath = database_path('seeders/Operation_Command_Center_COT_RSUI_v7620.html');
        if (file_exists($htmlPath)) {
            $html = file_get_contents($htmlPath);
            preg_match('/const COT_DB\s*=\s*(\{.*?\});/s', $html, $matches);
            if (!empty($matches)) {
                $db = json_decode($matches[1], true);
                if (isset($db['tindakan'])) {
                    $tindakanNames = $c->tindakan->pluck('nama')->toArray();
                    
                    // Clear existing package BMHP to prevent duplicates
                    $c->tambahanBmhp()->where('jenis', 'paket')->delete();

                    foreach ($db['tindakan'] as $item) {
                        foreach ($tindakanNames as $tName) {
                            if (strcasecmp($item['nama'], $tName) === 0) {
                                $bmhpItems = $item['bmhp'] ?? [];
                                foreach ($bmhpItems as $bItem) {
                                    $c->tambahanBmhp()->create([
                                        'nama' => $bItem['n'],
                                        'qty' => $bItem['q'] ?? 1,
                                        'harga' => $bItem['h'] ?? 0,
                                        'jenis' => 'paket'
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        }

        // Determine units based on penjamin and lokasi
        $units = ['Admin COT', 'Farmasi'];

        $c->addAudit("Broadcast Workflow Engine", "Diteruskan ke: " . implode(', ', $units) . '.', "Sistem");

        $this->recomputeState($c);

        return response()->json(['success' => true, 'message' => 'Kasus berhasil diajukan']);
    }

    public function cancel(Request $request, $id)
    {
        $c = OperationCase::findOrFail($id);

        $activeRole = session('role', Auth::user()->role);
        if ($activeRole === 'Nurse' && !in_array($c->status, ['Draft', 'Returned'])) {
            return response()->json(['success' => false, 'message' => 'Nurse tidak diperbolehkan membatalkan kasus yang sudah dikirim/diajukan.'], 403);
        }

        $c->status = 'Cancelled';
        $c->save();

        $note = $request->note ?: $request->reason ?: 'Kasus dibatalkan.';
        $c->addAudit("Case dibatalkan", $note, 'Nurse');
        $this->recomputeState($c);

        return response()->json(['success' => true, 'message' => 'Kasus berhasil dibatalkan']);
    }

    // Individual role action methods (reflecting role action JS methods)
    // Individual role action methods (reflecting role action JS methods)
    public function vaAction(Request $request, $id)
    {
        $c = OperationCase::with(['va'])->findOrFail($id);
        if ($c->penjamin === 'Umum') {
            return response()->json(['success' => false, 'message' => 'Aksi VA hanya diperbolehkan untuk kasus dengan Penjamin Asuransi.'], 403);
        }

        $va = $c->va ?: $c->va()->firstOrCreate([]);
        $action = $request->action;

        // Handle file uploads if any
        if ($request->hasFile('files')) {
            $attachments = $va->attachments ?: [];
            $uploadedNames = [];
            foreach ($request->file('files') as $file) {
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('attachments'), $filename);
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => '/attachments/' . $filename
                ];
                $uploadedNames[] = $file->getClientOriginalName();
            }
            $va->attachments = $attachments;
            $va->save();
            $c->addAudit("VA melampirkan berkas", implode(', ', $uploadedNames), "VA");
        }

        // Handle checklist save
        if ($request->has('checklist')) {
            $va->checklist = $request->checklist ?: [];
            $va->save();
        }

        if ($action === 'ajukan1') {
            $va->kelas = $request->kelas ?: $c->kelas_perawatan;
            $va->golongan = $request->golongan ?: $c->golongan;
            
            $rincian = $request->rincian ?: [];
            if (is_string($rincian)) {
                $rincian = json_decode($rincian, true) ?: [];
            }
            $va->estimasi_rincian = $rincian;
            
            $va->estimasi_total = $request->total ?: 0;
            $va->stage1_done = true;
            $va->done = false; // CM must approve first
            $va->save();

            $c->addAudit(
                "VA mengajukan estimasi biaya ke Case Manager",
                "Golongan {$va->golongan} / {$va->kelas} — Total Rp " . number_format($va->estimasi_total, 0, ',', '.') . ". " . ($request->note ?: ''),
                "VA"
            );
        } elseif ($action === 'revisi1') {
            $va->stage1_done = false;
            $va->save();
            $c->status = 'Returned';
            $c->save();
            $c->addAudit("VA meminta revisi data ke Nurse", $request->note ?: '', "VA");
        } elseif ($action === 'mulai') {
            $va->decision = 'DalamKonfirmasi';
            $va->decision_note = $request->note ?: 'Pengajuan estimasi dikirim ke pihak asuransi.';
            $va->save();
            $c->addAudit("VA mulai verifikasi — pengajuan estimasi ke asuransi", $va->decision_note, "VA");
        } elseif ($action === 'berkasBelumLengkap') {
            $va->berkas_belum_lengkap = true;
            $va->decision = 'DalamKonfirmasi';
            $va->decision_note = $request->note ?: 'Menunggu kelengkapan berkas.';
            $va->save();
            $c->addAudit("VA menandai berkas belum lengkap", $request->note ?: '', "VA");
        } elseif ($action === 'berkasLengkap') {
            $va->berkas_belum_lengkap = false;
            $va->decision_note = $request->note ?: 'Berkas dilengkapi.';
            $va->save();
            $c->addAudit("Berkas dilengkapi — kembali ke VA untuk diproses", $request->note ?: '', "Nurse");
        } elseif ($action === 'disetujui') {
            $va->decision = 'Disetujui';
            $va->decision_note = $request->note ?: '';
            $va->stage2_done = true;
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
        if ($c->penjamin !== 'Umum') {
            return response()->json(['success' => false, 'message' => 'Aksi Kasir hanya diperbolehkan untuk kasus dengan Penjamin Umum.'], 403);
        }

        $kasir = $c->kasir ?: $c->kasir()->firstOrCreate([]);
        $action = $request->action;

        if ($action === 'mulai') {
            $c->addAudit("Kasir mulai proses administrasi pasien umum", "", "Kasir");
        } elseif ($action === 'selesai1') {
            $kasir->stage1_done = true;
            $kasir->total_estimasi = $request->total_estimasi ?: 0;
            $kasir->note = $request->note ?: '';
            $kasir->save();
            $c->addAudit("Kasir menyelesaikan estimasi tahap awal ke Case Manager", "Estimasi: Rp " . number_format($kasir->total_estimasi, 0, ',', '.') . ". " . $kasir->note, "Kasir");
        } elseif ($action === 'revisi1') {
            $kasir->stage1_done = false;
            $kasir->save();
            $c->status = 'Returned';
            $c->save();
            $c->addAudit("Kasir meminta revisi ke Nurse", $request->note ?: '', "Kasir");
        } elseif ($action === 'selesai2') {
            $kasir->stage2_done = true;
            $kasir->done = true;
            $kasir->note2 = $request->note ?: '';
            $kasir->save();
            $c->addAudit("Kasir menyelesaikan administrasi akhir (pasca approval) — diteruskan ke CS", $kasir->note2, "Kasir");
        }

        $this->recomputeState($c);
        return response()->json(['success' => true, 'message' => 'Aksi Kasir berhasil diproses']);
    }

    public function adruAction(Request $request, $id)
    {
        $c = OperationCase::with(['adru'])->findOrFail($id);
        if ($c->penjamin !== 'Umum') {
            return response()->json(['success' => false, 'message' => 'Aksi ADRU COT hanya diperbolehkan untuk kasus dengan Penjamin Umum.'], 403);
        }

        $adru = $c->adru ?: $c->adru()->firstOrCreate([]);
        $action = $request->action;

        if ($action === 'mulai') {
            $c->addAudit("ADRU COT mulai menghitung estimasi biaya", "", "ADRU");
        } elseif ($action === 'ajukan1') {
            $adru->stage1_done = true;
            $adru->estimasi = $request->estimasi ?: '';
            $adru->note = $request->note ?: '';
            $adru->save();
            $c->addAudit(
                "ADRU COT mengajukan estimasi ke Case Manager",
                "Estimasi: {$adru->estimasi}. " . ($request->note ?: ''),
                "ADRU"
            );
        } elseif ($action === 'revisi1') {
            $adru->stage1_done = false;
            $adru->save();
            $c->status = 'Returned';
            $c->save();
            $c->addAudit("ADRU COT meminta revisi ke Nurse", $request->note ?: '', "ADRU");
        } elseif ($action === 'konfirmasi2') {
            $adru->stage2_done = true;
            $adru->done = true;
            $adru->confirm_note = $request->note ?: '';
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
        $farmasi = $c->farmasi ?: $c->farmasi()->firstOrCreate([]);
        $action = $request->action;

        if ($action === 'mulai') {
            $c->addAudit("Farmasi mulai review BMHP/obat", "", "Farmasi");
        } elseif ($action === 'setuju') {
            $farmasi->done = true;
            $farmasi->note = $request->note ?: '';
            $farmasi->save();

            // Save edited items
            if ($request->has('items')) {
                $c->tambahanBmhp()->delete();
                foreach ($request->items as $item) {
                    if (!empty($item['nama'])) {
                        $c->tambahanBmhp()->create([
                            'nama' => $item['nama'],
                            'qty' => $item['qty'] ?? 1,
                            'harga' => $item['harga'] ?? 0,
                            'jenis' => $item['jenis'] ?? 'tambahan'
                        ]);
                    }
                }
            }

            $c->addAudit("Farmasi menyetujui paket BMHP/obat", $farmasi->note, "Farmasi");
        } elseif ($action === 'save_items') {
            if ($request->has('items')) {
                $c->tambahanBmhp()->delete();
                foreach ($request->items as $item) {
                    if (!empty($item['nama'])) {
                        $c->tambahanBmhp()->create([
                            'nama' => $item['nama'],
                            'qty' => $item['qty'] ?? 1,
                            'harga' => $item['harga'] ?? 0,
                            'jenis' => $item['jenis'] ?? 'tambahan'
                        ]);
                    }
                }
            }
            $c->addAudit("Farmasi memperbarui daftar BMHP/obat", $request->note ?: '', "Farmasi");
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
        $adminCot = $c->adminCot ?: $c->adminCot()->firstOrCreate([]);
        $action = $request->action;

        if ($action === 'prelim') {
            $adminCot->prelim_done = true;
            $adminCot->save();

            // Save new instruments if provided
            $auditAlat = [];
            if ($request->has('alat')) {
                $c->alat()->delete();
                foreach ($request->alat as $aItem) {
                    if (is_array($aItem)) {
                        if (!empty($aItem['nama'])) {
                            $c->alat()->create([
                                'nama' => $aItem['nama'],
                                'harga' => $aItem['harga'] ?? 0
                            ]);
                            $auditAlat[] = $aItem['nama'];
                        }
                    } else {
                        if (!empty($aItem)) {
                            $defaultTarif = 0;
                            $masterAlat = \App\Models\AlatKhusus::where('nama', $aItem)->first();
                            if ($masterAlat) {
                                $defaultTarif = $masterAlat->tarif;
                            }
                            $c->alat()->create([
                                'nama' => $aItem,
                                'harga' => $defaultTarif
                            ]);
                            $auditAlat[] = $aItem;
                        }
                    }
                }
            }

            $c->addAudit(
                "Admin COT menentukan kebutuhan alat (awal)",
                "Alat: " . implode(', ', $auditAlat ?? []),
                "Admin COT"
            );
        } elseif ($action === 'save_tools') {
            if ($request->has('alat')) {
                $c->alat()->delete();
                foreach ($request->alat as $aItem) {
                    if (is_array($aItem) && !empty($aItem['nama'])) {
                        $c->alat()->create([
                            'nama' => $aItem['nama'],
                            'harga' => $aItem['harga'] ?? 0
                        ]);
                    }
                }
            }
            $c->addAudit("Admin COT memperbarui daftar alat khusus & harga", $request->note ?: '', "Admin COT");
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
        } elseif ($action === 'revisi_nurse') {
            $c->status = 'Returned';
            $c->save();
            $c->addAudit("Admin COT meminta revisi data ke Nurse", $request->note ?: '', "Admin COT");
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
        $cm = $c->caseManager ?: $c->caseManager()->firstOrCreate([]);
        $action = $request->action;

        if ($request->filled('golongan')) {
            $c->golongan = $request->golongan;
            $c->save();
        }

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
                $c->va->stage1_done = false;
                $c->va->done = false;
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
        $cs = $c->cs ?: $c->cs()->firstOrCreate([]);
        $action = $request->action;

        if ($action === 'hubungi') {
            $cs->decision = 'DalamKonfirmasi';
            $cs->decision_note = $request->note ?: 'Menunggu respon pasien.';
            $cs->follow_up_due = \Carbon\Carbon::now()->addHours(24)->toDateTimeString();
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

        } elseif ($action === 'revisi') {
            $c->status = 'Returned';
            $c->save();
            $c->addAudit("CS meminta revisi data ke Nurse", $request->note ?: '', "CS");
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
        $c = OperationCase::with(['va', 'tindakan', 'alat.masterAlat', 'tambahanBmhp', 'kasir', 'adru'])->findOrFail($id);
        
        $total = 0;
        $rincian = [];
        
        if ($c->penjamin === 'Asuransi') {
            $total = $c->va ? $c->va->estimasi_total : 0;
            $rincian = ($c->va && $c->va->estimasi_rincian) ? $c->va->estimasi_rincian : [];
        } else {
            // Pasien Umum
            if ($c->kasir && $c->kasir->total_estimasi > 0) {
                $total = $c->kasir->total_estimasi;
                $rincian = [
                    ['komponen' => 'Estimasi Tarif Administrasi Awal (Kasir)', 'nilai' => $total]
                ];
            } elseif ($c->adru && $c->adru->estimasi) {
                $numericVal = (float)str_replace(['Rp', '.', ',', ' '], '', $c->adru->estimasi);
                $total = $numericVal;
                $rincian = [
                    ['komponen' => 'Estimasi Tarif Kamar & Jasa COT (ADRU)', 'nilai' => $total]
                ];
            }
        }
        
        $dpjpNames = $c->dpjp->pluck('nama')->implode(', ') ?: '-';
        $tindakanNames = $c->tindakan->pluck('nama')->implode(', ') ?: '-';
        
        $rowsHtml = '';
        $num = 1;

        // A. Jasa Medis
        if (!empty($rincian)) {
            $rowsHtml .= "<tr><td colspan=\"3\" style=\"background:#eee; font-weight:bold; font-size:12.5px;\">A. Estimasi Jasa Medis / Awal</td></tr>";
            foreach ($rincian as $r) {
                $komponen = htmlspecialchars($r['komponen'] ?? '');
                $nilai = number_format($r['nilai'] ?? 0, 0, ',', '.');
                $rowsHtml .= "<tr><td style=\"width:28px;\">{$num}</td><td>{$komponen}</td><td style=\"text-align:right;\">{$nilai}</td></tr>";
                $num++;
            }
        }

        // B. Alat Khusus
        $alatKhusus = $c->alat;
        $totalAlat = 0;
        if ($alatKhusus->isNotEmpty()) {
            $rowsHtml .= "<tr><td colspan=\"3\" style=\"background:#eee; font-weight:bold; font-size:12.5px;\">B. Alat Khusus</td></tr>";
            foreach ($alatKhusus as $a) {
                $price = $a->harga > 0 ? $a->harga : ($a->masterAlat ? $a->masterAlat->tarif : 0);
                $totalAlat += $price;
                $priceFormatted = number_format($price, 0, ',', '.');
                $alatName = htmlspecialchars($a->nama);
                $rowsHtml .= "<tr><td style=\"width:28px;\">{$num}</td><td>Instrument: {$alatName}</td><td style=\"text-align:right;\">{$priceFormatted}</td></tr>";
                $num++;
            }
        }

        // C. Obat & BMHP
        $bmhpList = $c->tambahanBmhp;
        $totalBmhp = 0;
        if ($bmhpList->isNotEmpty()) {
            $rowsHtml .= "<tr><td colspan=\"3\" style=\"background:#eee; font-weight:bold; font-size:12.5px;\">C. Obat &amp; BMHP</td></tr>";
            foreach ($bmhpList as $t) {
                $subTotal = ($t->qty ?: 1) * ($t->harga ?: 0);
                $totalBmhp += $subTotal;
                $subTotalFormatted = number_format($subTotal, 0, ',', '.');
                $bmhpName = htmlspecialchars($t->nama);
                $qty = $t->qty ?: 1;
                $hargaSatuan = number_format($t->harga ?: 0, 0, ',', '.');
                $typeLabel = $t->jenis === 'paket' ? 'Paket' : 'Tambahan';
                $rowsHtml .= "<tr><td style=\"width:28px;\">{$num}</td><td>[{$typeLabel}] {$bmhpName} (x{$qty} @ Rp {$hargaSatuan})</td><td style=\"text-align:right;\">{$subTotalFormatted}</td></tr>";
                $num++;
            }
        }

        if (empty($rincian) && $alatKhusus->isEmpty() && $bmhpList->isEmpty()) {
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
        
        $grandTotal = $total + $totalAlat + $totalBmhp;
        $totalRupiah = number_format($grandTotal, 0, ',', '.');

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
        <td colspan="2" style="text-align:right;">TOTAL PERKIRAAN BIAYA (GRAND TOTAL)</td>
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

    private function getParsedDatabase()
    {
        $htmlPath = database_path('seeders/Operation_Command_Center_COT_RSUI_v7620.html');
        $data = [
            'cotDb' => [],
            'masterTarifDb' => [],
            'paketBmhpDb' => [],
            'nonpaketBmhpDb' => []
        ];

        if (file_exists($htmlPath)) {
            $html = file_get_contents($htmlPath);

            if (preg_match('/const COT_DB\s*=\s*(\{.*?\});/s', $html, $matches)) {
                $data['cotDb'] = json_decode($matches[1], true) ?: [];
            }

            if (preg_match('/const MASTER_TARIF_DB\s*=\s*(\{.*?\});/s', $html, $matches)) {
                $data['masterTarifDb'] = json_decode($matches[1], true) ?: [];
            }

            if (preg_match('/const PAKET_BMHP_DB\s*=\s*(\[.*?\]);/s', $html, $matches)) {
                $data['paketBmhpDb'] = json_decode($matches[1], true) ?: [];
            }

            if (preg_match('/const NONPAKET_BMHP_DB\s*=\s*(\[.*?\]);/s', $html, $matches)) {
                $data['nonpaketBmhpDb'] = json_decode($matches[1], true) ?: [];
            }
        }

        return $data;
    }

    public function estimasiMandiri()
    {
        $db = $this->getParsedDatabase();
        $mappings = \App\Models\GuarantorMapping::all();
        return view('estimasi.mandiri', compact('db', 'mappings'));
    }

    public function saveEstimasiHistory(Request $request)
    {
        $validated = $request->validate([
            'rm' => 'nullable|string',
            'nama' => 'nullable|string',
            'tindakan' => 'nullable|string',
            'penjamin' => 'nullable|string',
            'guarantor' => 'nullable|string',
            'golongan' => 'nullable|string',
            'kelas' => 'nullable|string',
            'total_estimasi' => 'required|numeric',
            'rincian' => 'nullable|array'
        ]);

        $history = EstimasiHistory::create($validated);
        return response()->json(['success' => true, 'id' => $history->id]);
    }

    public function estimasiHistory()
    {
        $history = EstimasiHistory::latest()->get();
        return view('estimasi.history', compact('history'));
    }

    public function deleteEstimasiHistory($id)
    {
        $history = EstimasiHistory::findOrFail($id);
        $history->delete();
        return response()->json(['success' => true]);
    }

    public function clearEstimasiHistory()
    {
        $history = EstimasiHistory::truncate();
        return response()->json(['success' => true]);
    }

    public function guarantorMapping()
    {
        $db = $this->getParsedDatabase();
        $tarifDb = $db['masterTarifDb'] ?: [];
        $mappings = \App\Models\GuarantorMapping::all();
        return view('estimasi.guarantor', compact('mappings', 'tarifDb'));
    }

    public function saveGuarantorMapping(Request $request)
    {
        $mappings = $request->input('mappings', []);

        DB::beginTransaction();
        try {
            GuarantorMapping::truncate();
            foreach ($mappings as $m) {
                if (empty($m['pola'])) continue;
                GuarantorMapping::create([
                    'pola' => $m['pola'],
                    'kelompok_tarif' => $m['kelompok_tarif'] ?? '2026',
                    'cob' => filter_var($m['cob'] ?? false, FILTER_VALIDATE_BOOLEAN)
                ]);
            }
            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function roleManagement()
    {
        $roles = \App\Models\RolePermission::all();
        return view('admin.role_management', compact('roles'));
    }

    public function saveRolePermissions(Request $request)
    {
        $perms = $request->input('permissions', []);

        DB::beginTransaction();
        try {
            foreach ($perms as $roleId => $data) {
                $role = RolePermission::where('role_id', $roleId)->first();
                if ($role) {
                    $role->update([
                        'label' => $data['label'] ?? $role->label,
                        'menus' => $data['menus'] ?? []
                    ]);
                }
            }
            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function addRole(Request $request)
    {
        $request->validate([
            'role_id' => 'required|string|unique:role_permissions,role_id',
            'label' => 'required|string',
        ]);

        $role = RolePermission::create([
            'role_id' => $request->role_id,
            'label' => $request->label,
            'menus' => ['dashboard', 'monitoring', 'roles']
        ]);

        return response()->json(['success' => true]);
    }

    public function disclaimer()
    {
        return view('dashboard.disclaimer');
    }

    public function dashboard()
    {
        return redirect()->route('dashboard');
    }

    public function schedule()
    {
        $cases = OperationCase::with(['dpjp', 'operators', 'tindakan', 'alat', 'adminCot', 'va'])
            ->get()
            ->map(function($c) {
                return $this->formatCaseForSchedule($c);
            })
            ->toArray();

        // Load configs and resources from settings database
        $slotConfigSetting = \App\Models\PrototypeSetting::where('key', 'slotConfigs')->first();
        $slotConfigs = $slotConfigSetting ? ($slotConfigSetting->value ?? []) : [];
        if (! is_array($slotConfigs)) {
            $slotConfigs = [];
        }

        $resourceMasterSetting = \App\Models\PrototypeSetting::where('key', 'resourceMaster')->first();
        $resourceMaster = $resourceMasterSetting ? ($resourceMasterSetting->value ?? []) : [
            ['nama' => 'C-Arm', 'keterangan' => 'Orthopaedi / Urologi / Bedah Saraf', 'total' => 2, 'harga' => 1500000, 'maintenance' => 0],
            ['nama' => 'Mikroskop Bedah Zeiss', 'keterangan' => 'Bedah Saraf / Mata', 'total' => 1, 'harga' => 2500000, 'maintenance' => 0],
            ['nama' => 'Laparoskopi Tower Stryker', 'keterangan' => 'Bedah Anak / Digestif / Obgyn', 'total' => 1, 'harga' => 2000000, 'maintenance' => 0],
            ['nama' => 'USG Intraoperatif Aloka', 'keterangan' => 'Bedah Saraf qq Oncology', 'total' => 1, 'harga' => 800000, 'maintenance' => 0],
            ['nama' => 'Endoskopi Spine Joimax', 'keterangan' => 'Orthopaedi qq Spine', 'total' => 1, 'harga' => 3000000, 'maintenance' => 0],
        ];
        if (! is_array($resourceMaster)) {
            $resourceMaster = [];
        }

        // totMinutes stored as integer or array — safely unwrap
        $totMinutesSetting = \App\Models\PrototypeSetting::where('key', 'totMinutes')->first();
        $rawTot = $totMinutesSetting ? $totMinutesSetting->value : null;
        if (is_array($rawTot)) {
            $totMinutes = (int) ($rawTot[0] ?? 45);
        } else {
            $totMinutes = (int) ($rawTot ?? 45);
        }
        if ($totMinutes <= 0) {
            $totMinutes = 45;
        }

        // Active role for the view (controls what UI the user can see)
        $activeRole = session('role', optional(Auth::user())->role ?? 'Viewer');

        $doctors = \App\Models\Doctor::orderBy('nama')->get()->toArray();

        return view('schedule.index', compact('cases', 'slotConfigs', 'resourceMaster', 'totMinutes', 'activeRole', 'doctors'));
    }

    public function dragReschedule(Request $request, $id)
    {
        $realRole = Auth::user()->role;
        $activeRole = session('role', $realRole);
        if ($activeRole !== 'AdminCOT' && !in_array($realRole, ['SuperAdmin', 'Administrator'])) {
            return response()->json(['success' => false, 'message' => 'Hanya Admin COT yang diperbolehkan memindah jadwal.'], 403);
        }

        $c = OperationCase::with(['adminCot'])->findOrFail($id);
        $adminCot = $c->adminCot ?: $c->adminCot()->firstOrCreate(['required' => true]);

        $prevTanggal = $adminCot->tanggal_fix ? $adminCot->tanggal_fix->format('Y-m-d') : '-';
        $prevJam = $adminCot->jam_fix ?: '-';
        $prevRuang = $adminCot->kamar_operasi ?: '-';

        $newTanggal = $request->tanggal;
        $newJam = $request->jam;
        $newRuang = $request->ruang;

        $adminCot->tanggal_fix = $newTanggal;
        $adminCot->jam_fix = $newJam;
        $adminCot->kamar_operasi = $newRuang;
        $adminCot->final_done = true;
        $adminCot->save();

        if ($c->raw_data) {
            $data = json_decode($c->raw_data, true) ?: [];
            if (isset($data['adminCot'])) {
                $data['adminCot']['finalDone'] = true;
                $data['adminCot']['jadwal']['tanggal'] = $newTanggal;
                $data['adminCot']['jadwal']['jam'] = $newJam;
                $data['adminCot']['jadwal']['ruang'] = $newRuang;
                $c->raw_data = json_encode($data);
                $c->save();
            }
        }

        $c->addAudit(
            "Reschedule via Timeline (Drag & Drop)",
            "Jadwal dipindahkan: [{$prevRuang}] {$prevTanggal} {$prevJam} ➔ [{$newRuang}] {$newTanggal} {$newJam}",
            "Admin COT"
        );

        return response()->json([
            'success' => true,
            'message' => 'Jadwal berhasil dipindahkan.',
            'case' => $this->formatCaseForSchedule($c)
        ]);
    }

    public function saveScheduleSettings(Request $request)
    {
        $realRole = Auth::user()->role;
        $activeRole = session('role', $realRole);
        if ($activeRole !== 'AdminCOT' && !in_array($realRole, ['SuperAdmin', 'Administrator'])) {
            return response()->json(['success' => false, 'message' => 'Hanya Admin COT yang diperbolehkan mengubah konfigurasi.'], 403);
        }

        if ($request->has('totMinutes')) {
            \App\Models\PrototypeSetting::updateOrCreate(
                ['key' => 'totMinutes'],
                ['value' => (int)$request->totMinutes]
            );
        }

        if ($request->has('slotConfigs')) {
            \App\Models\PrototypeSetting::updateOrCreate(
                ['key' => 'slotConfigs'],
                ['value' => $request->slotConfigs]
            );
        }

        if ($request->has('resourceMaster')) {
            \App\Models\PrototypeSetting::updateOrCreate(
                ['key' => 'resourceMaster'],
                ['value' => $request->resourceMaster]
            );
        }

        return response()->json(['success' => true, 'message' => 'Pengaturan berhasil disimpan.']);
    }

    public function markTindakanSelesai($id)
    {
        $realRole = Auth::user()->role;
        $activeRole = session('role', $realRole);
        if ($activeRole !== 'AdminCOT' && !in_array($realRole, ['SuperAdmin', 'Administrator'])) {
            return response()->json(['success' => false, 'message' => 'Hanya Admin COT yang diperbolehkan menyelesaikan tindakan.'], 403);
        }

        $c = OperationCase::with(['adminCot'])->findOrFail($id);

        $data = json_decode($c->raw_data, true) ?: [];
        if (!isset($data['adminCot'])) {
            $data['adminCot'] = [];
        }
        $data['adminCot']['tindakanSelesai'] = true;
        $c->raw_data = json_encode($data);
        $c->save();

        $c->addAudit("Tindakan Operasi selesai dilakukan", "Selesai di Kamar: " . ($c->adminCot ? $c->adminCot->kamar_operasi : '-'), "Admin COT");

        return response()->json(['success' => true, 'message' => 'Tindakan berhasil ditandai selesai.']);
    }

    public function cancelTindakan(Request $request, $id)
    {
        $realRole = Auth::user()->role;
        $activeRole = session('role', $realRole);
        if ($activeRole !== 'AdminCOT' && !in_array($realRole, ['SuperAdmin', 'Administrator'])) {
            return response()->json(['success' => false, 'message' => 'Hanya Admin COT yang diperbolehkan membatalkan tindakan.'], 403);
        }

        $c = OperationCase::findOrFail($id);
        $c->status = 'Cancelled';
        $c->save();

        $c->addAudit("Tindakan Operasi dibatalkan", $request->note ?: 'Batal tindakan oleh Admin COT', "Admin COT");

        return response()->json(['success' => true, 'message' => 'Tindakan berhasil dibatalkan.']);
    }

    private function formatCaseForSchedule($c)
    {
        $adminCot = $c->adminCot;
        
        $estimasiJam = 2.0;
        if ($adminCot && $adminCot->estimasi_jam > 0) {
            $estimasiJam = (float)$adminCot->estimasi_jam;
        } else {
            $text = strtolower($c->estimasi_lama_operasi);
            $hours = 0;
            $minutes = 0;
            if (preg_match('/(\d+)\s*jam/', $text, $matches)) {
                $hours = (float)$matches[1];
            }
            if (preg_match('/(\d+)\s*menit/', $text, $matches)) {
                $minutes = (float)$matches[1];
            }
            if ($hours > 0 || $minutes > 0) {
                $estimasiJam = $hours + ($minutes / 60);
            }
        }

        $tindakanSelesai = false;
        if ($c->raw_data) {
            $data = json_decode($c->raw_data, true);
            $tindakanSelesai = $data['adminCot']['tindakanSelesai'] ?? false;
        }

        return [
            'id' => $c->id,
            'nama' => $c->nama,
            'rm' => $c->rm,
            'penjamin' => $c->penjamin,
            'status' => $c->status,
            'golongan' => $c->golongan ?: ($c->va ? $c->va->golongan : 'NON GOLONGAN'),
            'kelasPerawatan' => $c->kelas_perawatan ?: ($c->va ? $c->va->kelas : 'Kelas 3'),
            'dokterAnestesi' => $c->anestesi ?: '',
            'jenisOperasi' => $c->jenis_operasi ?: [],
            'dpjpList' => $c->dpjp->pluck('nama')->toArray(),
            'operatorList' => $c->operators->pluck('nama')->toArray(),
            'tindakanList' => $c->tindakan->pluck('nama')->toArray(),
            'estimasiLamaOperasi' => $c->estimasi_lama_operasi,
            'adminCot' => [
                'required' => ($c->lokasi_tindakan === 'COT'),
                'prelimDone' => $adminCot ? (bool)$adminCot->prelim_done : false,
                'finalDone' => $adminCot ? (bool)$adminCot->final_done : false,
                'tindakanSelesai' => $tindakanSelesai,
                'decision' => $adminCot ? $adminCot->decision : '',
                'jadwal' => [
                    'tanggal' => ($adminCot && $adminCot->tanggal_fix) ? $adminCot->tanggal_fix->format('Y-m-d') : null,
                    'jam' => $adminCot ? $adminCot->jam_fix : null,
                    'ruang' => $adminCot ? $adminCot->kamar_operasi : null,
                ],
                'alat' => $c->alat->pluck('nama')->toArray(),
                'estimasiJam' => $estimasiJam
            ]
        ];
    }

    public function rolesReference()
    {
        return view('dashboard.roles');
    }

    public function syncAllCases(Request $request)
    {
        $casesData = $request->input('cases', []);
        
        DB::beginTransaction();
        try {
            foreach ($casesData as $cData) {
                if (empty($cData['id'])) continue;

                $c = OperationCase::findOrNew($cData['id']);
                $c->id = $cData['id'];
                $c->nama = $cData['nama'] ?? '';
                $c->rm = $cData['rm'] ?? '';
                $c->jenis_kelamin = $cData['jenisKelamin'] ?? 'L';
                $c->tgl_lahir = $cData['tglLahir'] ?? null;
                $c->lokasi_pengajuan = isset($cData['lokasiPengajuan']) ? [$cData['lokasiPengajuan']] : [];
                $c->diagnosis = $cData['diagnosis'] ?? null;
                $c->jenis_operasi = $cData['jenisOperasi'] ?? [];
                $c->anestesi = $cData['anestesi'] ?? null;
                $c->anestesi_lainnya = $cData['anestesiLainnya'] ?? null;
                $c->tanggal_pilihan1 = !empty($cData['tanggalPilihan1']) ? $cData['tanggalPilihan1'] : null;
                $c->tanggal_pilihan2 = !empty($cData['tanggalPilihan2']) ? $cData['tanggalPilihan2'] : null;
                $c->jam_operasi = !empty($cData['jamOperasi']) ? $cData['jamOperasi'] : null;
                $c->estimasi_lama_operasi = $cData['estimasiLamaOperasi'] ?? null;
                $c->lokasi_tindakan = $cData['lokasiTindakan'] ?? 'COT';
                $c->lokasi_tindakan_lainnya = $cData['lokasiTindakanLainnya'] ?? null;
                $c->asal_pasien = $cData['asalPasien'] ?? null;
                $c->asal_pasien_lainnya = $cData['asalPasienLainnya'] ?? null;
                $c->ruang_pasca_operasi = $cData['ruangPascaOperasi'] ?? null;
                $c->ruang_pasca_operasi_lainnya = $cData['ruangPascaOperasiLainnya'] ?? null;
                $c->estimasi_rawat_inap = $cData['estimasiRawatInap'] ?? null;
                $c->penjamin = $cData['penjamin'] ?? 'Umum';
                $c->nama_guarantor = $cData['namaGuarantor'] ?? null;
                $c->kelas_perawatan = $cData['kelasPerawatan'] ?? null;
                $c->golongan = $cData['golongan'] ?? null;
                $c->spesialisasi_op = $cData['spesialisasiOp'] ?? null;
                $c->current_flow = $cData['currentFlow'] ?? 'Nurse';
                $c->status = $cData['status'] ?? 'Draft';
                $c->expensive_flag = $cData['expensiveFlag'] ?? false;
                
                // Store raw JSON payload
                $c->raw_data = json_encode($cData);
                $c->save();

                // Sync DPJPs
                $c->dpjp()->delete();
                if (isset($cData['dpjpList'])) {
                    foreach ($cData['dpjpList'] as $index => $dpjpName) {
                        if (!empty($dpjpName)) {
                            $c->dpjp()->create(['nama' => $dpjpName, 'urutan' => $index]);
                        }
                    }
                }

                // Sync Operators
                $c->operators()->delete();
                if (isset($cData['operatorList'])) {
                    foreach ($cData['operatorList'] as $index => $opName) {
                        if (!empty($opName)) {
                            $c->operators()->create([
                                'nama' => $opName,
                                'spesialisasi' => $cData['operatorSpesialisasi'][$index] ?? null,
                                'urutan' => $index
                            ]);
                        }
                    }
                }

                // Sync Tindakan
                $c->tindakan()->delete();
                if (isset($cData['tindakanList'])) {
                    foreach ($cData['tindakanList'] as $index => $tName) {
                        if (!empty($tName)) {
                            $c->tindakan()->create(['nama' => $tName, 'urutan' => $index]);
                        }
                    }
                }

                // Sync Alat Khusus
                $c->alat()->delete();
                if (isset($cData['adminCot']['alat'])) {
                    foreach ($cData['adminCot']['alat'] as $alatItem) {
                        $aName = is_string($alatItem) ? $alatItem : ($alatItem['nama'] ?? '');
                        $aHarga = is_array($alatItem) ? ($alatItem['harga'] ?? 0) : 0;
                        if (!empty($aName)) {
                            $c->alat()->create([
                                'nama' => $aName,
                                'harga' => $aHarga
                            ]);
                        }
                    }
                }

                // Sync Tambahan BMHP
                $c->tambahanBmhp()->delete();
                if (isset($cData['tambahanBMHP'])) {
                    foreach ($cData['tambahanBMHP'] as $tb) {
                        if (!empty($tb['jenis'])) {
                            $c->tambahanBmhp()->create([
                                'nama' => $tb['spesifikasi'] ?? $tb['jenis'],
                                'qty' => $tb['qty'] ?? 1,
                                'harga' => $tb['harga'] ?? 0,
                                'jenis' => $tb['jenis']
                            ]);
                        }
                    }
                }

                // Sync Audits
                $c->audit()->delete();
                if (isset($cData['audit'])) {
                    foreach ($cData['audit'] as $auditItem) {
                        $c->audit()->create([
                            'actor' => $auditItem['role'] ?? 'System',
                            'action' => $auditItem['action'] ?? '',
                            'note' => $auditItem['note'] ?? '',
                            'created_at' => isset($auditItem['ts']) ? \Carbon\Carbon::parse($auditItem['ts']) : now()
                        ]);
                    }
                }

                // Sync Workflow Tables
                // 1. VA
                if (isset($cData['va'])) {
                    $va = $c->va ?: $c->va()->create([]);
                    $va->kelas = $cData['va']['kelas'] ?? null;
                    $va->golongan = $cData['va']['golongan'] ?? null;
                    $va->decision = $cData['va']['decision'] ?? null;
                    $va->decision_note = $cData['va']['decisionNote'] ?? null;
                    $va->estimasi_total = $cData['va']['estimasiTotal'] ?? null;
                    $va->estimasi_rincian = $cData['va']['estimasiRincian'] ?? null;
                    $va->done = filter_var($cData['va']['stage2Done'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $va->stage1_done = filter_var($cData['va']['stage1Done'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $va->save();
                }

                // 2. Kasir
                if (isset($cData['kasir'])) {
                    $kasir = $c->kasir ?: $c->kasir()->create([]);
                    $kasir->decision = $cData['kasir']['decision'] ?? null;
                    $kasir->note = $cData['kasir']['decisionNote'] ?? $cData['kasir']['stage1Note'] ?? null;
                    $kasir->done = filter_var($cData['kasir']['stage2Done'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $kasir->stage1_done = filter_var($cData['kasir']['stage1Done'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $kasir->save();
                }

                // 3. ADRU
                if (isset($cData['adru'])) {
                    $adru = $c->adru ?: $c->adru()->create([]);
                    $adru->decision = $cData['adru']['decision'] ?? null;
                    $adru->note = $cData['adru']['decisionNote'] ?? $cData['adru']['stage1Note'] ?? null;
                    $adru->done = filter_var($cData['adru']['stage2Done'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $adru->stage1_done = filter_var($cData['adru']['stage1Done'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $adru->save();
                }

                // 4. Farmasi
                if (isset($cData['farmasi'])) {
                    $farmasi = $c->farmasi ?: $c->farmasi()->create([]);
                    $farmasi->note = $cData['farmasi']['note'] ?? null;
                    $farmasi->done = filter_var($cData['farmasi']['done'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $farmasi->save();
                }

                // 5. Admin COT
                if (isset($cData['adminCot'])) {
                    $adminCot = $c->adminCot ?: $c->adminCot()->create([]);
                    $adminCot->required = filter_var($cData['adminCot']['required'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $adminCot->prelim_done = filter_var($cData['adminCot']['prelimDone'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $adminCot->final_done = filter_var($cData['adminCot']['finalDone'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $adminCot->decision = $cData['adminCot']['decision'] ?? null;
                    $adminCot->decision_note = $cData['adminCot']['decisionNote'] ?? null;
                    $adminCot->tanggal_fix = isset($cData['adminCot']['jadwal']['tanggal']) ? $cData['adminCot']['jadwal']['tanggal'] : null;
                    $adminCot->jam_fix = isset($cData['adminCot']['jadwal']['jam']) ? $cData['adminCot']['jadwal']['jam'] : null;
                    $adminCot->kamar_operasi = isset($cData['adminCot']['jadwal']['ruang']) ? $cData['adminCot']['jadwal']['ruang'] : null;
                    $adminCot->save();
                }

                // 6. Case Manager
                if (isset($cData['caseManager'])) {
                    $caseManager = $c->caseManager ?: $c->caseManager()->create([]);
                    $caseManager->decision = $cData['caseManager']['decision'] ?? null;
                    $caseManager->return_to = $cData['caseManager']['returnTo'] ?? null;
                    $caseManager->instruksi = $cData['caseManager']['instruksi'] ?? null;
                    $caseManager->done = filter_var($cData['caseManager']['done'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $caseManager->save();
                }

                // 7. CS
                if (isset($cData['cs'])) {
                    $cs = $c->cs ?: $c->cs()->create([]);
                    $cs->decision = $cData['cs']['decision'] ?? null;
                    $cs->decision_note = $cData['cs']['decisionNote'] ?? null;
                    $cs->done = filter_var($cData['cs']['done'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $cs->save();
                }
            }
            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function savePatients(Request $request)
    {
        $patients = $request->input('patients', []);
        DB::beginTransaction();
        try {
            foreach ($patients as $rm => $p) {
                if (empty($p['rm']) || empty($p['nama'])) continue;
                Pasien::updateOrCreate(
                    ['rm' => $p['rm']],
                    [
                        'nama' => $p['nama'],
                        'jenis_kelamin' => $p['jenisKelamin'] ?? 'L',
                        'tgl_lahir' => $p['tglLahir'] ?? null
                    ]
                );
            }
            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function saveSetting(Request $request, $key)
    {
        $value = $request->input('list') ?? $request->input('value') ?? $request->input('minutes') ?? $request->all();
        \App\Models\PrototypeSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
        return response()->json(['success' => true]);
    }

    public function uploadAttachment(Request $request)
    {
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('uploads'), $fileName);
            $url = asset('uploads/' . $fileName);
            return response()->json([
                'success' => true,
                'file' => [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'data' => $url
                ]
            ]);
        }
        return response()->json(['success' => false, 'message' => 'File tidak ditemukan']);
    }


    private function getBootstrapData()
    {
        $cases = OperationCase::all()->map(function ($c) {
            return $this->formatCase($c);
        })->toArray();

        $patients = Pasien::all()->mapWithKeys(function ($p) {
            return [$p->rm => [
                'nama' => $p->nama,
                'rm' => $p->rm,
                'jenisKelamin' => $p->jenis_kelamin,
                'tglLahir' => $p->tgl_lahir
            ]];
        })->toArray();

        $guarantorMappings = \App\Models\GuarantorMapping::all()->map(function ($gm) {
            return [
                'pola' => $gm->pola,
                'kelompokTarif' => $gm->kelompok_tarif,
                'cob' => (bool)$gm->cob
            ];
        })->toArray();

        $rolePermissions = \App\Models\RolePermission::all()->mapWithKeys(function ($rp) {
            return [$rp->role_id => $rp->menus ?: []];
        })->toArray();

        $estimasiHistories = EstimasiHistory::latest()->get()->map(function ($eh) {
            return [
                'id' => $eh->id,
                'rm' => $eh->rm,
                'nama' => $eh->nama,
                'tindakan' => $eh->tindakan,
                'penjamin' => $eh->penjamin,
                'guarantor' => $eh->guarantor,
                'golongan' => $eh->golongan,
                'kelas' => $eh->kelas,
                'total_estimasi' => $eh->total_estimasi,
                'rincian' => is_string($eh->rincian) ? json_decode($eh->rincian, true) : ($eh->rincian ?: []),
                'createdAt' => $eh->created_at ? $eh->created_at->toIso8601String() : null
            ];
        })->toArray();

        $slotConfigs = \App\Models\PrototypeSetting::where('key', 'slot_config')->first()?->value ?: [];
        $resourceMaster = \App\Models\PrototypeSetting::where('key', 'resource_master')->first()?->value ?: [];
        $totMinutes = \App\Models\PrototypeSetting::where('key', 'tot_minutes')->first()?->value ?? 60;
        $estimasiTemplates = \App\Models\PrototypeSetting::where('key', 'estimasi_templates')->first()?->value ?: [];
        $alatHistory = \App\Models\PrototypeSetting::where('key', 'alat_history')->first()?->value ?: [];
        $attachments = \App\Models\PrototypeSetting::where('key', 'attachments')->first()?->value ?: [];
        $doctors = \App\Models\Doctor::all()->map(function ($d) {
            return [
                'nama' => $d->nama,
                'namaGelar' => $d->nama_gelar,
                'spesialis' => $d->spesialis
            ];
        })->toArray();

        return compact('cases', 'patients', 'guarantorMappings', 'rolePermissions', 'estimasiHistories', 'slotConfigs', 'resourceMaster', 'totMinutes', 'estimasiTemplates', 'alatHistory', 'attachments', 'doctors');
    }

    private function formatCase($c)
    {
        if ($c->raw_data) {
            $data = json_decode($c->raw_data, true);
            if (is_array($data)) {
                $data['status'] = $c->status;
                $data['currentFlow'] = $c->current_flow;
                return $data;
            }
        }
        
        $va = $c->va;
        $kasir = $c->kasir;
        $adru = $c->adru;
        $farmasi = $c->farmasi;
        $adminCot = $c->adminCot;
        $caseManager = $c->caseManager;
        $cs = $c->cs;

        return [
            'id' => $c->id,
            'nama' => $c->nama,
            'rm' => $c->rm,
            'jenisKelamin' => $c->jenis_kelamin,
            'tglLahir' => $c->tgl_lahir,
            'createdAt' => $c->created_at ? $c->created_at->toIso8601String() : null,
            'lokasiPengajuan' => $c->lokasi_pengajuan ? ($c->lokasi_pengajuan[0] ?? '') : '',
            'diagnosis' => $c->diagnosis,
            'jenisOperasi' => $c->jenis_operasi ?: [],
            'anestesi' => $c->anestesi,
            'anestesiLainnya' => $c->anestesi_lainnya,
            'tanggalPilihan1' => $c->tanggal_pilihan1 ? $c->tanggal_pilihan1->format('Y-m-d') : null,
            'tanggalPilihan2' => $c->tanggal_pilihan2 ? $c->tanggal_pilihan2->format('Y-m-d') : null,
            'jamOperasi' => $c->jam_operasi,
            'estimasiLamaOperasi' => $c->estimasi_lama_operasi,
            'lokasiTindakan' => $c->lokasi_tindakan,
            'lokasiTindakanLainnya' => $c->lokasi_tindakan_lainnya,
            'asalPasien' => $c->asal_pasien,
            'asalPasienLainnya' => $c->asal_pasien_lainnya,
            'ruangPascaOperasi' => $c->ruang_pasca_operasi,
            'ruangPascaOperasiLainnya' => $c->ruang_pasca_operasi_lainnya,
            'estimasiRawatInap' => $c->estimasi_rawat_inap,
            'penjamin' => $c->penjamin,
            'namaGuarantor' => $c->nama_guarantor,
            'kelasPerawatan' => $c->kelas_perawatan,
            'golongan' => $c->golongan,
            'spesialisasiOp' => $c->spesialisasi_op,
            'status' => $c->status,
            'currentFlow' => $c->current_flow,
            'dpjpList' => $c->dpjp_list,
            'operatorList' => $c->operator_list,
            'operatorSpesialisasi' => $c->operators->pluck('spesialisasi')->toArray(),
            'tindakanList' => $c->tindakan_list,
            
            'va' => [
                'active' => ($c->penjamin === 'Asuransi'),
                'stage1Done' => $va ? (bool)$va->stage1_done : false,
                'stage1Note' => $va ? $va->note : '',
                'estimasi' => $va ? 'Rp ' . number_format($va->estimasi_total, 0, ',', '.') : '',
                'golongan' => $va ? $va->golongan : '',
                'kelas' => $va ? $va->kelas : '',
                'estimasiRincian' => $va ? ($va->estimasi_rincian ?: []) : [],
                'estimasiTotal' => $va ? $va->estimasi_total : 0,
                'stage2Done' => $va ? (bool)$va->done : false,
                'stage2Note' => $va ? $va->decision_note : '',
                'decision' => $va ? $va->decision : '',
                'decisionNote' => $va ? $va->decision_note : '',
                'berkasBelumLengkap' => false,
                'attachments' => [],
            ],
            'kasir' => [
                'active' => ($c->penjamin === 'Umum' && $c->lokasi_tindakan !== 'COT'),
                'stage1Done' => $kasir ? (bool)$kasir->stage1_done : false,
                'stage1Note' => $kasir ? $kasir->note : '',
                'stage2Done' => $kasir ? (bool)$kasir->done : false,
                'stage2Note' => $kasir ? $kasir->note : '',
                'decision' => $kasir ? $kasir->decision : '',
            ],
            'adru' => [
                'active' => ($c->penjamin === 'BPJS' || ($c->penjamin === 'Umum' && $c->lokasi_tindakan === 'COT')),
                'stage1Done' => $adru ? (bool)$adru->stage1_done : false,
                'stage1Note' => $adru ? $adru->note : '',
                'estimasi' => $adru ? $adru->note : '',
                'stage2Done' => $adru ? (bool)$adru->done : false,
                'stage2Note' => $adru ? $adru->note : '',
                'decision' => $adru ? $adru->decision : '',
                'patientConfirmed' => $adru ? (bool)$adru->done : false,
            ],
            'farmasi' => [
                'done' => $farmasi ? (bool)$farmasi->done : false,
                'note' => $farmasi ? $farmasi->note : '',
            ],
            'adminCot' => [
                'required' => ($c->lokasi_tindakan === 'COT'),
                'prelimDone' => $adminCot ? (bool)$adminCot->prelim_done : false,
                'finalDone' => $adminCot ? (bool)$adminCot->final_done : false,
                'jadwal' => [
                    'tanggal' => $adminCot ? $adminCot->tanggal_fix : null,
                    'jam' => $adminCot ? $adminCot->jam_fix : null,
                    'ruang' => $adminCot ? $adminCot->kamar_operasi : null,
                ],
                'prevJadwal' => null,
                'alat' => $c->alat->pluck('nama')->toArray(),
                'decision' => $adminCot ? $adminCot->decision : '',
                'decisionNote' => $adminCot ? $adminCot->decision_note : '',
                'tindakanSelesai' => false,
                'tindakanSelesaiAt' => '',
                'estimasiJam' => null,
            ],
            'caseManager' => [
                'done' => $caseManager ? (bool)$caseManager->done : false,
                'note' => $caseManager ? $caseManager->instruksi : '',
                'decision' => $caseManager ? $caseManager->decision : '',
                'returnTo' => $caseManager ? $caseManager->return_to : '',
                'instruksi' => $caseManager ? $caseManager->instruksi : '',
            ],
            'cs' => [
                'active' => ($c->penjamin === 'Asuransi' || ($c->penjamin === 'Umum' && $c->lokasi_tindakan !== 'COT')),
                'done' => $cs ? (bool)$cs->done : false,
                'note' => $cs ? $cs->decision_note : '',
                'decision' => $cs ? $cs->decision : '',
                'decisionNote' => $cs ? $cs->decision_note : '',
                'contactAt' => '',
                'followUpDue' => '',
            ],
            'audit' => $c->audit->map(function ($a) {
                return [
                    'ts' => $a->created_at ? $a->created_at->toIso8601String() : null,
                    'role' => $a->actor,
                    'action' => $a->action,
                    'note' => $a->note ?: '',
                ];
            })->toArray(),
        ];
    }

    private function buildRawDataFromRequest(Request $request, $caseId, $existingRawData = null)
    {
        $dob = null;
        if ($request->filled('dobY') && $request->filled('dobM') && $request->filled('dobD')) {
            $dob = $request->dobY . '-' . str_pad($request->dobM, 2, '0', STR_PAD_LEFT) . '-' . str_pad($request->dobD, 2, '0', STR_PAD_LEFT);
        }

        $existing = [];
        if ($existingRawData) {
            $existing = json_decode($existingRawData, true) ?: [];
        }

        // Determine expensive flag based on manual entries flags
        $expensive = false;
        $alatList = [];
        if ($request->has('alat')) {
            foreach ($request->alat as $index => $aName) {
                if (!empty($aName)) {
                    $flag = $request->alatFlag[$index] ?? 'Hijau';
                    if ($flag === 'Merah') {
                        $expensive = true;
                    }
                    $masterAlat = \App\Models\AlatKhusus::where('nama', $aName)->first();
                    $price = $masterAlat ? $masterAlat->tarif : 0;
                    $alatList[] = [
                        'nama' => $aName,
                        'harga' => $price,
                        'flag' => $flag
                    ];
                }
            }
        }

        $tambahanBMHP = [];
        if ($request->has('tambahanBmhpNama')) {
            foreach ($request->tambahanBmhpNama as $index => $tbName) {
                if (!empty($tbName)) {
                    $qty = (int)($request->tambahanBmhpQty[$index] ?? 1);
                    $flag = $request->tambahanBmhpFlag[$index] ?? 'Hijau';
                    if ($flag === 'Merah') {
                        $expensive = true;
                    }
                    $masterBmhp = \App\Models\PaketBmhp::where('nama', $tbName)->first();
                    $price = $masterBmhp ? $masterBmhp->tarif : 0;
                    $tambahanBMHP[] = [
                        'jenis' => $tbName,
                        'spesifikasi' => '',
                        'qty' => $qty,
                        'harga' => $price,
                        'flag' => $flag
                    ];
                }
            }
        }

        // Default or existing sub-arrays
        $va = $existing['va'] ?? [
            'active' => false,
            'stage1Done' => false,
            'stage1Note' => '',
            'estimasi' => '',
            'golongan' => '',
            'kelas' => '',
            'estimasiRincian' => [],
            'estimasiTotal' => 0,
            'stage2Done' => false,
            'stage2Note' => '',
            'decision' => '',
            'decisionNote' => '',
            'berkasBelumLengkap' => false,
            'attachments' => [],
        ];
        $kasir = $existing['kasir'] ?? [
            'active' => false,
            'stage1Done' => false,
            'stage1Note' => '',
            'stage2Done' => false,
            'stage2Note' => '',
            'decision' => '',
        ];
        $adru = $existing['adru'] ?? [
            'active' => false,
            'stage1Done' => false,
            'stage1Note' => '',
            'estimasi' => '',
            'stage2Done' => false,
            'stage2Note' => '',
            'decision' => '',
            'patientConfirmed' => false,
        ];
        $farmasi = $existing['farmasi'] ?? [
            'done' => false,
            'note' => '',
        ];
        $caseManager = $existing['caseManager'] ?? [
            'done' => false,
            'note' => '',
            'decision' => '',
            'returnTo' => '',
            'instruksi' => '',
        ];
        $cs = $existing['cs'] ?? [
            'active' => false,
            'done' => false,
            'note' => '',
            'decision' => '',
            'decisionNote' => '',
            'contactAt' => '',
            'followUpDue' => '',
        ];

        $audit = $existing['audit'] ?? [
            [
                'ts' => now()->toIso8601String(),
                'role' => 'Nurse',
                'action' => 'Case dibuat (Draft)',
                'note' => 'Data diinput Nurse berdasarkan Form Penjadwalan Tindakan.'
            ]
        ];

        $adminCot = $existing['adminCot'] ?? [
            'required' => ($request->lokasiTindakan === 'COT'),
            'prelimDone' => false,
            'finalDone' => false,
            'jadwal' => [
                'tanggal' => null,
                'jam' => null,
                'ruang' => null
            ]
        ];
        $adminCot['alat'] = $alatList;

        $cData = [
            'id' => $caseId,
            'nama' => $request->nama,
            'rm' => $request->rm,
            'jenisKelamin' => $request->jenisKelamin ?? 'L',
            'tglLahir' => $dob,
            'createdAt' => $existing['createdAt'] ?? now()->toIso8601String(),
            'lokasiPengajuan' => $request->lokasiPengajuan,
            'diagnosis' => $request->diagnosis,
            'jenisOperasi' => $request->jenisOperasi ?? [],
            'anestesi' => $request->anestesi,
            'anestesiLainnya' => $request->anestesiLainnya,
            'tanggalPilihan1' => $request->tanggalPilihan1 ?: null,
            'tanggalPilihan2' => $request->tanggalPilihan2 ?: null,
            'jamOperasi' => $request->jamOperasi ?: null,
            'estimasiLamaOperasiJam' => $request->estimasiLamaOperasiJam,
            'estimasiLamaOperasiMenit' => $request->estimasiLamaOperasiMenit,
            'lokasiTindakan' => $request->lokasiTindakan ?? 'COT',
            'lokasiTindakanLainnya' => $request->lokasiTindakanLainnya,
            'asalPasien' => $request->asalPasien,
            'asalPasienLainnya' => $request->asalPasienLainnya,
            'ruangPascaOperasi' => $request->ruangPascaOperasi,
            'ruangPascaOperasiLainnya' => $request->ruangPascaOperasiLainnya,
            'estimasiRawatInap' => $request->estimasiRawatInap,
            'penjamin' => $request->penjamin ?? 'Umum',
            'namaGuarantor' => $request->namaGuarantor,
            'kelasPerawatan' => $request->kelasPerawatan ?? 'Kelas 3',
            'golongan' => $request->golongan,
            'spesialisasiOp' => $request->spesialisasiOp,
            
            // JKN fields
            'hakKelas' => $request->hakKelas,
            'rujukanBpjs' => $request->rujukanBpjs,
            
            // Pre-Op fields
            'preOpAnestesi' => $request->preOpAnestesi ?: 'Tidak',
            'preOpLab' => $request->preOpLab ?: 'Tidak',
            'preOpRad' => $request->preOpRad ?: 'Tidak',
            'preOpKonsul' => $request->preOpKonsul ?: 'Tidak',
            'preOpKonsulDetail' => $request->preOpKonsulDetail ?: '',

            // Lists
            'dpjpList' => $request->dpjp ?? [],
            'operatorList' => $request->operator ?? [],
            'tindakanList' => $request->tindakan ?? [],
            'operatorSpesialisasi' => $request->operatorSpesialisasi ?? [],

            // Workflow sections
            'va' => $va,
            'kasir' => $kasir,
            'adru' => $adru,
            'farmasi' => $farmasi,
            'adminCot' => $adminCot,
            'caseManager' => $caseManager,
            'cs' => $cs,
            'audit' => $audit,
            'expensiveFlag' => $expensive
        ];

        return [
            'raw_data' => json_encode($cData),
            'expensive_flag' => $expensive
        ];
    }

    public function uploadCaseAttachment(Request $request, $id)
    {
        $request->validate([
            'file' => 'required|file|max:2048', // 2048 KB = 2 MB
        ]);

        $c = OperationCase::findOrFail($id);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            
            // Store under public/uploads/cases/{case_id}
            $targetDir = public_path('uploads/cases/' . $c->id);
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            
            $file->move($targetDir, $fileName);
            $filePath = '/uploads/cases/' . $c->id . '/' . $fileName;

            // Update raw_data attachments list
            $rawData = json_decode($c->raw_data, true) ?: [];
            if (!isset($rawData['attachments'])) {
                $rawData['attachments'] = [];
            }

            $rawData['attachments'][] = [
                'id' => uniqid(),
                'name' => $file->getClientOriginalName(),
                'path' => $filePath,
                'uploaded_at' => now()->toIso8601String()
            ];

            $c->raw_data = json_encode($rawData);
            $c->save();

            $c->addAudit("Mengunggah dokumen pengajuan awal: " . $file->getClientOriginalName(), null, "Nurse");

            return response()->json([
                'success' => true,
                'message' => 'File berhasil diunggah.',
                'attachments' => $rawData['attachments']
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Gagal mengunggah file.']);
    }

    public function deleteCaseAttachment(Request $request, $id)
    {
        $request->validate([
            'attachment_id' => 'required|string',
        ]);

        $c = OperationCase::findOrFail($id);
        $rawData = json_decode($c->raw_data, true) ?: [];
        $attachments = $rawData['attachments'] ?? [];

        $filteredAttachments = [];
        $deletedFile = null;

        foreach ($attachments as $att) {
            if ($att['id'] === $request->attachment_id) {
                $deletedFile = $att;
                // Delete physical file if exists
                $fullPath = public_path($att['path']);
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            } else {
                $filteredAttachments[] = $att;
            }
        }

        $rawData['attachments'] = $filteredAttachments;
        $c->raw_data = json_encode($rawData);
        $c->save();

        if ($deletedFile) {
            $c->addAudit("Menghapus dokumen pengajuan awal: " . $deletedFile['name'], null, "Nurse");
        }

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berhasil dihapus.',
            'attachments' => $filteredAttachments
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\CaseAdminCot;
use App\Models\OperationCase;
use App\Models\PrototypeSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScheduleController extends Controller
{
    /**
     * Show the Monitoring Penjadwalan COT page.
     *
     * Passes the following to the view (matching the JS shape expected by index.blade.php):
     *  - $cases          : array of case objects in camelCase JS shape
     *  - $slotConfigs    : array of slot config objects
     *  - $resourceMaster : array of resource master objects
     *  - $totMinutes     : integer (Turn Over Time in minutes)
     *  - $activeRole     : string (current session role)
     */
    public function index(Request $request)
    {
        $activeRole = session('role', optional(Auth::user())->role ?? 'Viewer');

        // ── Cases ─────────────────────────────────────────────────────────────
        // Load ALL cases so the table view (which shows all statuses) can work.
        // The JS filters further by tab view (tabel shows all, timeline filters by date etc.)
        $rawCases = OperationCase::with([
                'adminCot', 'dpjp', 'operators', 'tindakan', 'alat',
            ])
            ->whereNotIn('status', ['Draft'])   // exclude bare drafts that haven't been submitted
            ->orderBy('created_at', 'desc')
            ->get();

        $cases = $rawCases->map(function ($c) {
            $ac = $c->adminCot;  // CaseAdminCot model or null

            // Build adminCot shape expected by JS
            $adminCotJs = null;
            if ($ac) {
                $adminCotJs = [
                    'required'        => (bool) $ac->required,
                    'prelimDone'      => (bool) $ac->prelim_done,
                    'finalDone'       => (bool) $ac->final_done,
                    'tindakanSelesai' => (bool) ($ac->tindakan_selesai ?? false),
                    'decision'        => $ac->decision,
                    'decisionNote'    => $ac->decision_note,
                    'jadwal'          => [
                        'tanggal' => $ac->tanggal_fix ? $ac->tanggal_fix->format('Y-m-d') : null,
                        'jam'     => $ac->jam_fix,
                        'ruang'   => $ac->kamar_operasi,
                    ],
                    'alat'            => $c->alat->pluck('nama')->filter()->values()->toArray(),
                    'estimasiJam'     => $this->parseEstimasiJam($c->estimasi_lama_operasi),
                ];
            }

            return [
                'id'                  => $c->id,
                'nama'                => $c->nama,
                'rm'                  => $c->rm,
                'penjamin'            => $c->penjamin,
                'status'              => $c->status,
                'golongan'            => $c->golongan,
                'kelasPerawatan'      => $c->kelas_perawatan,
                'dokterAnestesi'      => $c->anestesi === 'Lainnya' ? $c->anestesi_lainnya : $c->anestesi,
                'jenisOperasi'        => is_array($c->jenis_operasi) ? $c->jenis_operasi : [],
                'dpjpList'            => $c->dpjp->pluck('nama')->filter()->values()->toArray(),
                'operatorList'        => $c->operators->pluck('nama')->filter()->values()->toArray(),
                'tindakanList'        => $c->tindakan->pluck('nama')->filter()->values()->toArray(),
                'estimasiLamaOperasi' => $c->estimasi_lama_operasi,
                'adminCot'            => $adminCotJs,
            ];
        })->values()->toArray();

        // ── Slot Configs ──────────────────────────────────────────────────────
        $slotConfigRaw = PrototypeSetting::find('slot_configs');
        $slotConfigs   = $slotConfigRaw ? ($slotConfigRaw->value ?? []) : [];
        if (! is_array($slotConfigs)) {
            $slotConfigs = [];
        }

        // ── Resource Master ───────────────────────────────────────────────────
        $resourceRaw    = PrototypeSetting::find('resource_master');
        $resourceMaster = $resourceRaw ? ($resourceRaw->value ?? []) : [];
        if (! is_array($resourceMaster)) {
            $resourceMaster = [];
        }

        // ── Turn Over Time ────────────────────────────────────────────────────
        $totRaw     = PrototypeSetting::find('tot_minutes');
        $totMinutes = $totRaw ? (int) ($totRaw->value[0] ?? 45) : 45;
        if ($totMinutes <= 0) {
            $totMinutes = 45;
        }

        $doctors = \App\Models\Doctor::orderBy('nama')->get()->toArray();

        return view('schedule.index', compact(
            'cases',
            'slotConfigs',
            'resourceMaster',
            'totMinutes',
            'activeRole',
            'doctors'
        ));
    }

    /**
     * Convert estimasi lama operasi string (e.g. "3 Jam 30 Menit") to decimal hours.
     */
    private function parseEstimasiJam(?string $estimasi): float
    {
        if (! $estimasi) {
            return 1.0;
        }
        $jam   = 0;
        $menit = 0;
        if (preg_match('/(\d+)\s*Jam/i', $estimasi, $m)) {
            $jam = (int) $m[1];
        }
        if (preg_match('/(\d+)\s*Menit/i', $estimasi, $m)) {
            $menit = (int) $m[1];
        }
        return round($jam + ($menit / 60), 2) ?: 1.0;
    }

    /**
     * Save schedule settings (slot configs, resource master, TOT) from AJAX.
     */
    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'slot_configs'    => 'nullable|array',
            'resource_master' => 'nullable|array',
            'tot_minutes'     => 'nullable|integer|min:0|max:999',
        ]);

        if (array_key_exists('slot_configs', $data)) {
            PrototypeSetting::updateOrCreate(
                ['key' => 'slot_configs'],
                ['value' => $data['slot_configs'] ?? []]
            );
        }

        if (array_key_exists('resource_master', $data)) {
            PrototypeSetting::updateOrCreate(
                ['key' => 'resource_master'],
                ['value' => $data['resource_master'] ?? []]
            );
        }

        if (array_key_exists('tot_minutes', $data)) {
            PrototypeSetting::updateOrCreate(
                ['key' => 'tot_minutes'],
                ['value' => [$data['tot_minutes']]]
            );
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Drag-and-drop reschedule: update adminCot jadwal for a case.
     */
    public function dragReschedule(Request $request, string $caseId)
    {
        $data = $request->validate([
            'tanggal' => 'required|date',
            'jam'     => 'required|string|max:10',
            'ruang'   => 'required|string|max:50',
        ]);

        $ac = CaseAdminCot::where('case_id', $caseId)->first();
        if (! $ac) {
            return response()->json(['ok' => false, 'message' => 'Admin COT record not found.'], 404);
        }

        $ac->tanggal_fix    = $data['tanggal'];
        $ac->jam_fix        = $data['jam'];
        $ac->kamar_operasi  = $data['ruang'];
        $ac->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Mark a tindakan as completed.
     */
    public function markTindakanSelesai(Request $request, string $caseId)
    {
        $ac = CaseAdminCot::where('case_id', $caseId)->first();
        if (! $ac) {
            return response()->json(['ok' => false, 'message' => 'Admin COT record not found.'], 404);
        }

        $ac->tindakan_selesai = true;
        $ac->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Cancel a tindakan (set case status to Cancelled).
     */
    public function cancelTindakan(Request $request, string $caseId)
    {
        $case = OperationCase::findOrFail($caseId);
        $case->status = 'Cancelled';
        $case->save();

        return response()->json(['ok' => true]);
    }
}

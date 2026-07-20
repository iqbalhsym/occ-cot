<?php

namespace App\Http\Controllers;

use App\Models\OperationCase;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $activeRole = session('role', auth()->user() ? auth()->user()->role : 'Viewer');

        $totalQuery = OperationCase::query();
        $byPenjaminQueryAsuransi = OperationCase::where('penjamin', 'Asuransi');
        $byPenjaminQueryUmum = OperationCase::where('penjamin', 'Umum');

        if ($activeRole !== 'Nurse') {
            $totalQuery->where('status', '!=', 'Draft');
            $byPenjaminQueryAsuransi->where('status', '!=', 'Draft');
            $byPenjaminQueryUmum->where('status', '!=', 'Draft');
        }

        $total = $totalQuery->count();
        $aktif = OperationCase::whereIn('status', ['InProgress', 'Submitted'])->count();
        $selesai = OperationCase::where('status', 'Completed')->count();
        $returned = OperationCase::where('status', 'Returned')->count();

        $byPenjamin = [
            'Asuransi' => $byPenjaminQueryAsuransi->count(),
            'Umum' => $byPenjaminQueryUmum->count()
        ];

        // Status filter for timeline
        $status = $request->query('status', 'All');
        $timelineQuery = OperationCase::with(['dpjp', 'tindakan'])->orderBy('created_at', 'desc')->limit(20);
        if ($status !== 'All') {
            $timelineQuery->where('status', $status);
        }
        if ($activeRole !== 'Nurse') {
            $timelineQuery->where('status', '!=', 'Draft');
        }
        $timelineCases = $timelineQuery->get();

        // Query Priority / Overdue schedules (next 7 days, past, or submitted > 7 days ago and still active)
        $sevenDaysFromNow = now()->addDays(7)->format('Y-m-d');
        $sevenDaysAgoDateTime = now()->subDays(7);

        $priorityQuery = OperationCase::whereIn('status', ['InProgress', 'Submitted', 'Returned']);
        if ($activeRole !== 'Nurse') {
            $priorityQuery->where('status', '!=', 'Draft');
        }

        $priorityCases = $priorityQuery->where(function ($q) use ($sevenDaysFromNow, $sevenDaysAgoDateTime) {
                $q->where(function ($q2) use ($sevenDaysFromNow) {
                    $q2->whereHas('adminCot', function ($q3) use ($sevenDaysFromNow) {
                        $q3->whereNotNull('tanggal_fix')
                           ->where('tanggal_fix', '<=', $sevenDaysFromNow);
                    });
                })
                ->orWhere(function ($q2) use ($sevenDaysFromNow) {
                    $q2->whereNotNull('tanggal_pilihan1')
                       ->where('tanggal_pilihan1', '<=', $sevenDaysFromNow);
                })
                ->orWhere('created_at', '<=', $sevenDaysAgoDateTime);
            })
            ->with(['dpjp', 'tindakan', 'adminCot'])
            ->orderBy('created_at', 'asc')
            ->limit(30)
            ->get();

        return view('dashboard.index', compact('total', 'aktif', 'selesai', 'returned', 'byPenjamin', 'timelineCases', 'priorityCases', 'status'));
    }

    public function getStats()
    {
        $activeRole = session('role', auth()->user() ? auth()->user()->role : 'Viewer');

        $totalQuery = OperationCase::query();
        $byPenjaminQueryAsuransi = OperationCase::where('penjamin', 'Asuransi');
        $byPenjaminQueryUmum = OperationCase::where('penjamin', 'Umum');

        if ($activeRole !== 'Nurse') {
            $totalQuery->where('status', '!=', 'Draft');
            $byPenjaminQueryAsuransi->where('status', '!=', 'Draft');
            $byPenjaminQueryUmum->where('status', '!=', 'Draft');
        }

        $total = $totalQuery->count();
        $aktif = OperationCase::whereIn('status', ['InProgress', 'Submitted'])->count();
        $selesai = OperationCase::where('status', 'Completed')->count();
        $returned = OperationCase::where('status', 'Returned')->count();

        $byPenjamin = [
            'Asuransi' => $byPenjaminQueryAsuransi->count(),
            'Umum' => $byPenjaminQueryUmum->count()
        ];

        return response()->json([
            'total' => $total,
            'aktif' => $aktif,
            'selesai' => $selesai,
            'returned' => $returned,
            'byPenjamin' => $byPenjamin
        ]);
    }

    public function rolesReference()
    {
        return view('dashboard.roles');
    }
}

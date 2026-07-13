<?php

namespace App\Http\Controllers;

use App\Models\OperationCase;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $total = OperationCase::count();
        $aktif = OperationCase::whereIn('status', ['InProgress', 'Submitted'])->count();
        $selesai = OperationCase::where('status', 'Completed')->count();
        $returned = OperationCase::where('status', 'Returned')->count();

        $byPenjamin = [
            'Asuransi' => OperationCase::where('penjamin', 'Asuransi')->count(),
            'Umum' => OperationCase::where('penjamin', 'Umum')->count()
        ];

        // Sort cases by createdAt desc for the timeline, limited to the latest 20 cases for efficiency
        $timelineCases = OperationCase::with(['dpjp', 'tindakan'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return view('dashboard.index', compact('total', 'aktif', 'selesai', 'returned', 'byPenjamin', 'timelineCases'));
    }

    public function getStats()
    {
        $total = OperationCase::count();
        $aktif = OperationCase::whereIn('status', ['InProgress', 'Submitted'])->count();
        $selesai = OperationCase::where('status', 'Completed')->count();
        $returned = OperationCase::where('status', 'Returned')->count();

        $byPenjamin = [
            'Asuransi' => OperationCase::where('penjamin', 'Asuransi')->count(),
            'Umum' => OperationCase::where('penjamin', 'Umum')->count()
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

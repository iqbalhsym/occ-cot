<?php

namespace App\Http\Controllers;

use App\Models\OperationCase;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $cases = OperationCase::with(['dpjp', 'tindakan'])->get();

        $total = $cases->count();
        $aktif = $cases->filter(function ($c) {
            return in_array($c->status, ['InProgress', 'Submitted']);
        })->count();
        $selesai = $cases->where('status', 'Completed')->count();
        $returned = $cases->where('status', 'Returned')->count();

        $byPenjamin = [
            'Asuransi' => $cases->where('penjamin', 'Asuransi')->count(),
            'Umum' => $cases->where('penjamin', 'Umum')->count()
        ];

        // Sort cases by createdAt desc for the timeline
        $timelineCases = OperationCase::with(['dpjp', 'tindakan'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('dashboard.index', compact('total', 'aktif', 'selesai', 'returned', 'byPenjamin', 'timelineCases'));
    }

    public function getStats()
    {
        $cases = OperationCase::all();

        $total = $cases->count();
        $aktif = $cases->filter(function ($c) {
            return in_array($c->status, ['InProgress', 'Submitted']);
        })->count();
        $selesai = $cases->where('status', 'Completed')->count();
        $returned = $cases->where('status', 'Returned')->count();

        $byPenjamin = [
            'Asuransi' => $cases->where('penjamin', 'Asuransi')->count(),
            'Umum' => $cases->where('penjamin', 'Umum')->count()
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

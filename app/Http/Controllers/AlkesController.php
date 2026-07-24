<?php

namespace App\Http\Controllers;

use App\Models\AlkesKhusus;
use Illuminate\Http\Request;

class AlkesController extends Controller
{
    public function index(Request $request)
    {
        $query = AlkesKhusus::query();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where('nama', 'like', "%$s%")
                  ->orWhere('aturan_ruangan', 'like', "%$s%");
        }

        $alkes = $query->orderBy('nama')->paginate(20);
        $rooms = ["OT 1", "OT 2", "OT 3", "OT 4", "OT 5", "OT 6", "Hybrid", "OT lt 5", "IGD", "Cathlab"];

        return view('admin.alkes', compact('alkes', 'rooms'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'aturan_ruangan' => 'nullable|string|max:255',
            'allowed_rooms' => 'nullable|array',
        ]);

        $alkes = AlkesKhusus::create([
            'nama' => $request->nama,
            'aturan_ruangan' => $request->aturan_ruangan ?: '-',
            'allowed_rooms' => $request->allowed_rooms ?: []
        ]);

        return response()->json([
            'success' => true,
            'message' => "Alkes Khusus '{$alkes->nama}' berhasil ditambahkan.",
            'data' => $alkes
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'aturan_ruangan' => 'nullable|string|max:255',
            'allowed_rooms' => 'nullable|array',
        ]);

        $alkes = AlkesKhusus::findOrFail($id);
        $alkes->update([
            'nama' => $request->nama,
            'aturan_ruangan' => $request->aturan_ruangan ?: '-',
            'allowed_rooms' => $request->allowed_rooms ?: []
        ]);

        return response()->json([
            'success' => true,
            'message' => "Alkes Khusus '{$alkes->nama}' berhasil diperbarui.",
            'data' => $alkes
        ]);
    }

    public function destroy($id)
    {
        $alkes = AlkesKhusus::findOrFail($id);
        $name = $alkes->nama;
        $alkes->delete();

        return response()->json([
            'success' => true,
            'message' => "Alkes Khusus '{$name}' berhasil dihapus."
        ]);
    }
}

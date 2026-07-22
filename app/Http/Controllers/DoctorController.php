<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DoctorController extends Controller
{
    public function index(Request $request)
    {
        $query = Doctor::query();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('nama', 'like', "%$s%")
                  ->orWhere('nama_gelar', 'like', "%$s%")
                  ->orWhere('ksm', 'like', "%$s%")
                  ->orWhere('spesialis', 'like', "%$s%")
                  ->orWhere('konsultan', 'like', "%$s%")
                  ->orWhere('status', 'like', "%$s%");
            });
        }

        $doctors = $query->orderBy('nama')->paginate(20);

        return view('admin.doctors', compact('doctors'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'nama_gelar' => 'required|string|max:255',
            'spesialis' => 'nullable|string|max:255',
            'ksm' => 'nullable|string|max:255',
            'konsultan' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:255',
            'no_hp' => 'nullable|string|max:255',
        ]);

        $doc = Doctor::create($request->all());

        return response()->json([
            'success' => true,
            'message' => "Dokter '{$doc->nama}' berhasil ditambahkan.",
            'data' => $doc
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'nama_gelar' => 'required|string|max:255',
            'spesialis' => 'nullable|string|max:255',
            'ksm' => 'nullable|string|max:255',
            'konsultan' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:255',
            'no_hp' => 'nullable|string|max:255',
        ]);

        $doc = Doctor::findOrFail($id);
        $doc->update($request->all());

        return response()->json([
            'success' => true,
            'message' => "Dokter '{$doc->nama}' berhasil diperbarui.",
            'data' => $doc
        ]);
    }

    public function destroy($id)
    {
        $doc = Doctor::findOrFail($id);
        $name = $doc->nama;
        $doc->delete();

        return response()->json([
            'success' => true,
            'message' => "Dokter '{$name}' berhasil dihapus."
        ]);
    }
}

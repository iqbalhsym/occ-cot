<?php

namespace App\Http\Controllers;

use App\Models\Tindakan;
use App\Models\TindakanGolongan;
use App\Models\PaketBmhp;
use App\Models\AlatKhusus;
use Illuminate\Http\Request;

class MasterDataController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'database_master');
        $search = $request->query('search');

        $data = null;

        switch ($tab) {
            case 'database_master':
                $query = Tindakan::query();
                if ($search) {
                    $query->where('nama', 'like', "%{$search}%")
                          ->orWhere('golongan', 'like', "%{$search}%")
                          ->orWhere('spesialisasi', 'like', "%{$search}%")
                          ->orWhere('paket', 'like', "%{$search}%")
                          ->orWhere('paket_anestesi', 'like', "%{$search}%")
                          ->orWhere('alat', 'like', "%{$search}%");
                }
                $data = $query->orderBy('nama')->paginate(15)->appends($request->all());
                break;

            case 'master_tindakan':
                $query = TindakanGolongan::query();
                if ($search) {
                    $query->where('tindakan', 'like', "%{$search}%")
                          ->orWhere('operator', 'like', "%{$search}%")
                          ->orWhere('golongan', 'like', "%{$search}%");
                }
                $data = $query->orderBy('tindakan')->paginate(15)->appends($request->all());
                break;

            case 'master_paket_bmhp':
                $query = PaketBmhp::query();
                if ($search) {
                    $query->where('nama', 'like', "%{$search}%")
                          ->orWhere('tarif', 'like', "%{$search}%");
                }
                $data = $query->orderBy('nama')->paginate(15)->appends($request->all());
                break;

            case 'master_alat':
                $query = AlatKhusus::query();
                if ($search) {
                    $query->where('nama', 'like', "%{$search}%")
                          ->orWhere('tarif', 'like', "%{$search}%");
                }
                $data = $query->orderBy('nama')->paginate(15)->appends($request->all());
                break;
        }

        return view('admin.master', compact('data', 'tab', 'search'));
    }

    public function store(Request $request)
    {
        $tab = $request->input('tab');

        switch ($tab) {
            case 'database_master':
                $request->validate(['nama' => 'required|string|max:255']);
                $item = Tindakan::create($request->only(['nama', 'golongan', 'spesialisasi', 'paket', 'paket_anestesi', 'alat']));
                break;

            case 'master_tindakan':
                $request->validate(['tindakan' => 'required|string|max:255']);
                $item = TindakanGolongan::create($request->only(['tindakan', 'operator', 'golongan']));
                break;

            case 'master_paket_bmhp':
                $request->validate([
                    'nama' => 'required|string|max:255',
                    'tarif' => 'nullable|numeric'
                ]);
                $item = PaketBmhp::create($request->only(['nama', 'tarif']));
                break;

            case 'master_alat':
                $request->validate([
                    'nama' => 'required|string|max:255',
                    'tarif' => 'nullable|numeric'
                ]);
                $item = AlatKhusus::create($request->only(['nama', 'tarif']));
                break;

            default:
                return response()->json(['success' => false, 'message' => 'Tab tidak valid.'], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil ditambahkan.',
            'data' => $item
        ]);
    }

    public function update(Request $request, $id)
    {
        $tab = $request->input('tab');

        switch ($tab) {
            case 'database_master':
                $request->validate(['nama' => 'required|string|max:255']);
                $item = Tindakan::findOrFail($id);
                $item->update($request->only(['nama', 'golongan', 'spesialisasi', 'paket', 'paket_anestesi', 'alat']));
                break;

            case 'master_tindakan':
                $request->validate(['tindakan' => 'required|string|max:255']);
                $item = TindakanGolongan::findOrFail($id);
                $item->update($request->only(['tindakan', 'operator', 'golongan']));
                break;

            case 'master_paket_bmhp':
                $request->validate([
                    'nama' => 'required|string|max:255',
                    'tarif' => 'nullable|numeric'
                ]);
                $item = PaketBmhp::findOrFail($id);
                $item->update($request->only(['nama', 'tarif']));
                break;

            case 'master_alat':
                $request->validate([
                    'nama' => 'required|string|max:255',
                    'tarif' => 'nullable|numeric'
                ]);
                $item = AlatKhusus::findOrFail($id);
                $item->update($request->only(['nama', 'tarif']));
                break;

            default:
                return response()->json(['success' => false, 'message' => 'Tab tidak valid.'], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diperbarui.',
            'data' => $item
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $tab = $request->query('tab');

        switch ($tab) {
            case 'database_master':
                $item = Tindakan::findOrFail($id);
                break;

            case 'master_tindakan':
                $item = TindakanGolongan::findOrFail($id);
                break;

            case 'master_paket_bmhp':
                $item = PaketBmhp::findOrFail($id);
                break;

            case 'master_alat':
                $item = AlatKhusus::findOrFail($id);
                break;

            default:
                return response()->json(['success' => false, 'message' => 'Tab tidak valid.'], 400);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil dihapus.'
        ]);
    }
}

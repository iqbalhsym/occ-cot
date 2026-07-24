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

    public function testWa($id)
    {
        $doc = Doctor::findOrFail($id);
        if (empty($doc->no_hp)) {
            return response()->json([
                'success' => false,
                'message' => "Nomor HP dokter tidak terdaftar."
            ], 400);
        }

        $service = app(\App\Services\QiscusWaService::class);
        $doctorName = $doc->nama_gelar ?: $doc->nama;
        
        $messageText = "Halo {$doctorName},\n\nIni adalah pesan uji coba integrasi notifikasi WhatsApp Qiscus dari sistem COT OCC RSUI.\n\nKoneksi berhasil!";

        // Attempt to send template HSM notification (or fallback to plain text if session exists)
        $result = $service->sendTemplateNotification(
            $doc->no_hp, 
            'dokter_schedule_reminder', 
            [$doctorName, "OT 1", now()->locale('id')->isoFormat('D MMMM YYYY H:i') . " WIB", "Pasien Uji Coba", "Tindakan Medis Uji Coba"]
        );

        if (!$result['success']) {
            // Fallback to sending plain text message
            $result = $service->sendPlainMessage($doc->no_hp, $messageText);
        }

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => "Pesan uji coba berhasil dikirim ke {$doctorName}."
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => "Gagal mengirim WhatsApp: " . $result['message']
        ], 500);
    }
}

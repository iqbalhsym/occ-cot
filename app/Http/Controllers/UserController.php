<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('username')->get();
        
        $rolesList = [
            'SuperAdmin' => 'Super Admin',
            'Administrator' => 'Administrator',
            'Nurse' => 'Nurse',
            'VA' => 'VA (Asuransi)',
            'Kasir' => 'Kasir (Billing)',
            'ADRUCOT' => 'ADRU COT (Estimator)',
            'Farmasi' => 'Farmasi',
            'AdminCOT' => 'Admin COT',
            'CaseManager' => 'Case Manager (CM)',
            'CS' => 'Customer Service (CS)',
            'Viewer' => 'Viewer'
        ];

        return view('admin.users', compact('users', 'rolesList'));
    }

    public function updateRole(Request $request, $id)
    {
        $request->validate([
            'role' => 'required|string|in:SuperAdmin,Administrator,Nurse,VA,Kasir,ADRUCOT,Farmasi,AdminCOT,CaseManager,CS,Viewer'
        ]);

        $targetUser = User::findOrFail($id);
        $currentUser = Auth::user();

        // 1. mohammad.hud is frozen and cannot be changed or deleted
        if ($targetUser->username === 'mohammad.hud') {
            return response()->json([
                'success' => false,
                'message' => 'Peran Super Admin mohammad.hud bersifat permanen dan tidak dapat diubah.'
            ], 422);
        }

        // 2. Administrator cannot alter SuperAdmin users
        if ($currentUser->role === 'Administrator' && $targetUser->role === 'SuperAdmin') {
            return response()->json([
                'success' => false,
                'message' => 'Sebagai Administrator, Anda tidak dapat mengubah peran akun Super Admin.'
            ], 422);
        }

        // Apply changes
        $targetUser->role = $request->role;
        $targetUser->save();

        return response()->json([
            'success' => true,
            'message' => "Peran user '{$targetUser->username}' berhasil diubah menjadi {$request->role}."
        ]);
    }
}


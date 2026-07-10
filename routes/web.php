<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\PasienController;
use App\Http\Controllers\TindakanController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\UserController;

// Guest Routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::get('/refresh-captcha', [LoginController::class, 'refreshCaptcha'])->name('captcha.refresh');

// Authenticated Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });
    
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/roles-reference', [DashboardController::class, 'rolesReference'])->name('roles.reference');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Cases CRUD and Submissions
    Route::get('/cases', [CaseController::class, 'index'])->name('cases.index');
    Route::get('/cases/create', [CaseController::class, 'create'])->name('cases.create');
    Route::post('/cases', [CaseController::class, 'store'])->name('cases.store');
    Route::get('/cases/{id}', [CaseController::class, 'show'])->name('cases.show');
    Route::get('/cases/{id}/edit', [CaseController::class, 'edit'])->name('cases.edit');
    Route::put('/cases/{id}', [CaseController::class, 'update'])->name('cases.update');
    Route::post('/cases/{id}/submit', [CaseController::class, 'submit'])->name('cases.submit');
    Route::post('/cases/{id}/cancel', [CaseController::class, 'cancel'])->name('cases.cancel');

    // Workflow Actions
    Route::post('/cases/{id}/va', [CaseController::class, 'vaAction'])->name('cases.va');
    Route::post('/cases/{id}/kasir', [CaseController::class, 'kasirAction'])->name('cases.kasir');
    Route::post('/cases/{id}/adru', [CaseController::class, 'adruAction'])->name('cases.adru');
    Route::post('/cases/{id}/farmasi', [CaseController::class, 'farmasiAction'])->name('cases.farmasi');
    Route::post('/cases/{id}/admin-cot', [CaseController::class, 'adminCotAction'])->name('cases.admin-cot');
    Route::post('/cases/{id}/case-manager', [CaseController::class, 'caseManagerAction'])->name('cases.case-manager');
    Route::post('/cases/{id}/cs', [CaseController::class, 'csAction'])->name('cases.cs');

    // Estimation PDF Document View
    Route::get('/cases/{id}/download-estimasi', [CaseController::class, 'downloadEstimasi'])->name('cases.download-estimasi');

    // Master Data APIs
    Route::get('/api/patients/{rm}', [PasienController::class, 'lookup'])->name('api.patients.lookup');
    Route::get('/api/master-data', [TindakanController::class, 'getMasterData'])->name('api.master-data');
    Route::get('/api/tindakan/lookup', [TindakanController::class, 'lookupTindakan'])->name('api.tindakan.lookup');
    Route::post('/set-role', function(\Illuminate\Http\Request $request) {
        session(['role' => $request->role]);
        return response()->json(['success' => true]);
    });

    // Admin & SuperAdmin only Routes
    Route::middleware(['role:SuperAdmin,Administrator'])->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('admin.users');
        Route::post('/users/{id}/role', [UserController::class, 'updateRole'])->name('admin.users.update-role');
    });
});

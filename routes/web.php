<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\PasienController;
use App\Http\Controllers\TindakanController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\MasterDataController;

// Guest Routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::get('/refresh-captcha', [LoginController::class, 'refreshCaptcha'])->name('captcha.refresh');

// Authenticated Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/schedule', [CaseController::class, 'schedule'])->name('schedule.index');
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
    Route::post('/cases/{id}/upload-attachment', [CaseController::class, 'uploadCaseAttachment'])->name('cases.upload-attachment');
    Route::post('/cases/{id}/delete-attachment', [CaseController::class, 'deleteCaseAttachment'])->name('cases.delete-attachment');

    // Workflow Actions
    Route::post('/cases/{id}/va', [CaseController::class, 'vaAction'])->name('cases.va');
    Route::post('/cases/{id}/kasir', [CaseController::class, 'kasirAction'])->name('cases.kasir');
    Route::post('/cases/{id}/adru', [CaseController::class, 'adruAction'])->name('cases.adru');
    Route::post('/cases/{id}/farmasi', [CaseController::class, 'farmasiAction'])->name('cases.farmasi');
    Route::post('/cases/{id}/admin-cot', [CaseController::class, 'adminCotAction'])->name('cases.admin-cot');
    Route::post('/cases/{id}/case-manager', [CaseController::class, 'caseManagerAction'])->name('cases.case-manager');
    Route::post('/cases/{id}/cs', [CaseController::class, 'csAction'])->name('cases.cs');

    // Sync & Upload APIs
    Route::post('/api/cases/sync-all', [CaseController::class, 'syncAllCases']);
    Route::post('/api/patients/save', [CaseController::class, 'savePatients']);
    Route::post('/api/guarantor-mapping/save', [CaseController::class, 'saveGuarantorMapping']);
    Route::post('/api/role-permissions/save', [CaseController::class, 'saveRolePermissions']);
    Route::post('/api/slot-config/save', [CaseController::class, 'saveSetting'])->defaults('key', 'slot_config');
    Route::post('/api/resource-master/save', [CaseController::class, 'saveSetting'])->defaults('key', 'resource_master');
    Route::post('/api/tot-minutes/save', [CaseController::class, 'saveSetting'])->defaults('key', 'tot_minutes');
    Route::post('/api/estimasi-template/save', [CaseController::class, 'saveSetting'])->defaults('key', 'estimasi_templates');
    Route::post('/api/alat-history/save', [CaseController::class, 'saveSetting'])->defaults('key', 'alat_history');
    Route::post('/api/attachments/save', [CaseController::class, 'saveSetting'])->defaults('key', 'attachments');
    Route::post('/api/attachments/upload', [CaseController::class, 'uploadAttachment']);

    // Estimation PDF Document View
    Route::get('/cases/{id}/download-estimasi', [CaseController::class, 'downloadEstimasi'])->name('cases.download-estimasi');

    // Schedule Routes
    Route::post('/schedule/drag-reschedule/{id}', [CaseController::class, 'dragReschedule'])->name('schedule.drag-reschedule');
    Route::post('/schedule/settings/save', [CaseController::class, 'saveScheduleSettings'])->name('schedule.settings.save');
    Route::post('/schedule/tindakan-selesai/{id}', [CaseController::class, 'markTindakanSelesai'])->name('schedule.tindakan-selesai');
    Route::post('/schedule/batal-tindakan/{id}', [CaseController::class, 'cancelTindakan'])->name('schedule.batal-tindakan');

    // Master Data APIs
    Route::get('/api/patients/{rm}', [PasienController::class, 'lookup'])->name('api.patients.lookup');
    Route::get('/api/master-data', [TindakanController::class, 'getMasterData'])->name('api.master-data');
    Route::get('/api/tindakan/lookup', [TindakanController::class, 'lookupTindakan'])->name('api.tindakan.lookup');
    Route::get('/api/alat/lookup', [TindakanController::class, 'lookupAlat'])->name('api.alat.lookup');
    Route::post('/set-role', function(\Illuminate\Http\Request $request) {
        session(['role' => $request->role]);
        return response()->json(['success' => true]);
    });

    // Prototype Sync Routes
    Route::get('/estimasi-mandiri', [CaseController::class, 'estimasiMandiri'])->name('estimasi.mandiri');
    Route::post('/api/estimasi-history', [CaseController::class, 'saveEstimasiHistory']);
    Route::get('/estimasi-history', [CaseController::class, 'estimasiHistory'])->name('estimasi.history');
    Route::delete('/api/estimasi-history/{id}', [CaseController::class, 'deleteEstimasiHistory']);
    Route::post('/api/estimasi-history/clear', [CaseController::class, 'clearEstimasiHistory']);

    Route::get('/guarantor-mapping', [CaseController::class, 'guarantorMapping'])->name('guarantor.mapping');
    Route::post('/api/guarantor-mapping', [CaseController::class, 'saveGuarantorMapping']);

    Route::get('/role-management', [CaseController::class, 'roleManagement'])->name('role.management');
    Route::post('/api/role-permissions', [CaseController::class, 'saveRolePermissions']);
    Route::post('/api/role-permissions/add-role', [CaseController::class, 'addRole']);

    Route::get('/disclaimer', [CaseController::class, 'disclaimer'])->name('disclaimer');

    // Admin & SuperAdmin only Routes
    Route::middleware(['role:SuperAdmin,Administrator'])->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('admin.users');
        Route::post('/users/{id}/role', [UserController::class, 'updateRole'])->name('admin.users.update-role');
        
        Route::get('/admin/doctors', [DoctorController::class, 'index'])->name('admin.doctors');
        Route::post('/admin/doctors', [DoctorController::class, 'store'])->name('admin.doctors.store');
        Route::put('/admin/doctors/{id}', [DoctorController::class, 'update'])->name('admin.doctors.update');
        Route::delete('/admin/doctors/{id}', [DoctorController::class, 'destroy'])->name('admin.doctors.destroy');
        Route::post('/admin/doctors/{id}/test-wa', [DoctorController::class, 'testWa'])->name('admin.doctors.test-wa');

        Route::get('/admin/alkes', [AlkesController::class, 'index'])->name('admin.alkes');
        Route::post('/admin/alkes', [AlkesController::class, 'store'])->name('admin.alkes.store');
        Route::put('/admin/alkes/{id}', [AlkesController::class, 'update'])->name('admin.alkes.update');
        Route::delete('/admin/alkes/{id}', [AlkesController::class, 'destroy'])->name('admin.alkes.destroy');

        Route::get('/admin/master', [MasterDataController::class, 'index'])->name('admin.master');
        Route::post('/admin/master', [MasterDataController::class, 'store'])->name('admin.master.store');
        Route::put('/admin/master/{id}', [MasterDataController::class, 'update'])->name('admin.master.update');
        Route::delete('/admin/master/{id}', [MasterDataController::class, 'destroy'])->name('admin.master.destroy');
    });
});


<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tindakan;
use App\Models\TindakanGolongan;
use App\Models\PaketBmhp;
use App\Models\AlatKhusus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataCRUDTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'username' => 'admin.test',
            'name' => 'Admin Test',
            'email' => 'admin.test@rs.ui.ac.id',
            'role' => 'SuperAdmin'
        ]);

        $this->viewer = User::create([
            'username' => 'viewer.test',
            'name' => 'Viewer Test',
            'email' => 'viewer.test@rs.ui.ac.id',
            'role' => 'Viewer'
        ]);
    }

    public function test_guests_and_viewers_cannot_access_master_data_dashboard()
    {
        // Guest
        $response = $this->get('/admin/master');
        $response->assertRedirect('/login');

        // Viewer role
        $response = $this->actingAs($this->viewer)
            ->withSession(['role' => 'Viewer'])
            ->get('/admin/master');
        $response->assertStatus(403);
    }

    public function test_admin_can_access_master_data_dashboard()
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['role' => 'SuperAdmin'])
            ->get('/admin/master');

        $response->assertStatus(200);
        $response->assertSee('DATABASE_MASTER');
        $response->assertSee('MASTER_TINDAKAN');
        $response->assertSee('MASTER_PAKET_BMHP');
        $response->assertSee('MASTER_ALAT');
    }

    public function test_crud_operations_on_database_master_tindakan()
    {
        // 1. Create (Store)
        $response = $this->actingAs($this->admin)
            ->withSession(['role' => 'SuperAdmin'])
            ->postJson('/admin/master', [
                'tab' => 'database_master',
                'nama' => 'Test Operasi Baru',
                'golongan' => 'SEDANG',
                'spesialisasi' => 'Bedah',
                'paket' => 'DEBRIDEMENT',
                'paket_anestesi' => 'ANESTESI GA',
                'alat' => 'C-ARM'
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('tindakan', ['nama' => 'Test Operasi Baru']);

        $id = $response->json('data.id');

        // 2. Update
        $response = $this->actingAs($this->admin)
            ->withSession(['role' => 'SuperAdmin'])
            ->putJson("/admin/master/{$id}", [
                'tab' => 'database_master',
                'nama' => 'Test Operasi Diedit',
                'golongan' => 'BESAR',
                'spesialisasi' => 'Bedah Digestif'
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('tindakan', [
            'id' => $id,
            'nama' => 'Test Operasi Diedit',
            'golongan' => 'BESAR'
        ]);

        // 3. Delete
        $response = $this->actingAs($this->admin)
            ->withSession(['role' => 'SuperAdmin'])
            ->deleteJson("/admin/master/{$id}?tab=database_master");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('tindakan', ['id' => $id]);
    }

    public function test_crud_operations_on_master_tindakan_golongan()
    {
        // 1. Create
        $response = $this->actingAs($this->admin)
            ->withSession(['role' => 'SuperAdmin'])
            ->postJson('/admin/master', [
                'tab' => 'master_tindakan',
                'tindakan' => 'Aff Double J',
                'operator' => 'Urologi',
                'golongan' => 'KECIL'
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('tindakan_golongan', ['tindakan' => 'Aff Double J']);

        $id = $response->json('data.id');

        // 2. Update
        $response = $this->actingAs($this->admin)
            ->withSession(['role' => 'SuperAdmin'])
            ->putJson("/admin/master/{$id}", [
                'tab' => 'master_tindakan',
                'tindakan' => 'Aff Double J (per Sistoskopi)',
                'operator' => 'Urologi',
                'golongan' => 'SEDANG'
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('tindakan_golongan', [
            'id' => $id,
            'tindakan' => 'Aff Double J (per Sistoskopi)',
            'golongan' => 'SEDANG'
        ]);

        // 3. Delete
        $response = $this->actingAs($this->admin)
            ->withSession(['role' => 'SuperAdmin'])
            ->deleteJson("/admin/master/{$id}?tab=master_tindakan");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('tindakan_golongan', ['id' => $id]);
    }

    public function test_crud_operations_on_master_paket_bmhp()
    {
        // 1. Create
        $response = $this->actingAs($this->admin)
            ->withSession(['role' => 'SuperAdmin'])
            ->postJson('/admin/master', [
                'tab' => 'master_paket_bmhp',
                'nama' => 'PAKET TEST BMHP',
                'tarif' => 2500000
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('paket_bmhp', ['nama' => 'PAKET TEST BMHP']);

        $id = $response->json('data.id');

        // 2. Update
        $response = $this->actingAs($this->admin)
            ->withSession(['role' => 'SuperAdmin'])
            ->putJson("/admin/master/{$id}", [
                'tab' => 'master_paket_bmhp',
                'nama' => 'PAKET TEST BMHP DIEDIT',
                'tarif' => 3000000
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('paket_bmhp', [
            'id' => $id,
            'nama' => 'PAKET TEST BMHP DIEDIT',
            'tarif' => 3000000
        ]);

        // 3. Delete
        $response = $this->actingAs($this->admin)
            ->withSession(['role' => 'SuperAdmin'])
            ->deleteJson("/admin/master/{$id}?tab=master_paket_bmhp");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('paket_bmhp', ['id' => $id]);
    }

    public function test_crud_operations_on_master_alat_khusus()
    {
        // 1. Create
        $response = $this->actingAs($this->admin)
            ->withSession(['role' => 'SuperAdmin'])
            ->postJson('/admin/master', [
                'tab' => 'master_alat',
                'nama' => 'ALAT TEST KHUSUS',
                'tarif' => 500000
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('alat_khusus', ['nama' => 'ALAT TEST KHUSUS']);

        $id = $response->json('data.id');

        // 2. Update
        $response = $this->actingAs($this->admin)
            ->withSession(['role' => 'SuperAdmin'])
            ->putJson("/admin/master/{$id}", [
                'tab' => 'master_alat',
                'nama' => 'ALAT TEST KHUSUS DIEDIT',
                'tarif' => 750000
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('alat_khusus', [
            'id' => $id,
            'nama' => 'ALAT TEST KHUSUS DIEDIT',
            'tarif' => 750000
        ]);

        // 3. Delete
        $response = $this->actingAs($this->admin)
            ->withSession(['role' => 'SuperAdmin'])
            ->deleteJson("/admin/master/{$id}?tab=master_alat");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('alat_khusus', ['id' => $id]);
    }
}

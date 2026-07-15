<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\OperationCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowSaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_every_role_can_save_workflow_data()
    {
        // 1. Create a SuperAdmin user (can act as any role switcher)
        $user = User::create([
            'username' => 'sadmin.test',
            'name' => 'Sadmin Test',
            'role' => 'SuperAdmin',
            'password' => bcrypt('password')
        ]);

        // 2. Create an in-progress case
        $case = OperationCase::create([
            'id' => 'COT-202607-999',
            'nama' => 'Test Patient',
            'rm' => '999999',
            'penjamin' => 'Asuransi',
            'status' => 'InProgress',
            'lokasi_tindakan' => 'COT'
        ]);

        // 3. Test VA Action (Stage 1)
        $response = $this->actingAs($user)
                         ->postJson("/cases/{$case->id}/va", [
                             'action' => 'ajukan1',
                             'total' => 15000000,
                             'kelas' => 'Kelas 1',
                             'golongan' => 'SEDANG',
                             'rincian' => [['komponen' => 'Jasa Medis', 'nilai' => 15000000]],
                             'note' => 'VA Note'
                         ]);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Verify VA data saved in database
        $case->refresh();
        $this->assertTrue((bool)$case->va->stage1_done);
        $this->assertEquals(15000000, $case->va->estimasi_total);

        // 4. Test CM Action (Approve VA Stage 1)
        $response = $this->actingAs($user)
                         ->postJson("/cases/{$case->id}/case-manager", [
                             'action' => 'setuju',
                             'note' => 'CM Approved'
                         ]);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $case->refresh();
        $this->assertTrue((bool)$case->caseManager->done);

        // 5. Test VA Action (Stage 2 - checklist/decision)
        $response = $this->actingAs($user)
                         ->postJson("/cases/{$case->id}/va", [
                             'action' => 'disetujui',
                             'checklist' => ['Formulir Penjadwalan', 'Surat Pengantar DPJP'],
                             'note' => 'Approved guarantee'
                         ]);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $case->refresh();
        $this->assertTrue((bool)$case->va->done);
        $this->assertEquals('Disetujui', $case->va->decision);
        $this->assertEquals(['Formulir Penjadwalan', 'Surat Pengantar DPJP'], $case->va->checklist);

        // 6. Test CS Action (Follow up)
        $response = $this->actingAs($user)
                         ->postJson("/cases/{$case->id}/cs", [
                             'action' => 'hubungi',
                             'note' => 'Dihubungi pertama'
                         ]);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $case->refresh();
        $this->assertEquals('DalamKonfirmasi', $case->cs->decision);
        $this->assertNotNull($case->cs->follow_up_due);

        // 7. Test Farmasi Action (BMHP/Obat)
        $response = $this->actingAs($user)
                         ->postJson("/cases/{$case->id}/farmasi", [
                             'action' => 'setuju',
                             'note' => 'BMHP checked',
                             'items' => [
                                 ['nama' => 'Spuit 5cc', 'qty' => 10, 'harga' => 5000, 'jenis' => 'tambahan'],
                                 ['nama' => 'Infus Set', 'qty' => 2, 'harga' => 15000, 'jenis' => 'tambahan']
                             ]
                         ]);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $case->refresh();
        $this->assertTrue((bool)$case->farmasi->done);
        $this->assertCount(2, $case->tambahanBmhp);

        // 8. Test Admin COT (Prelim/Alat)
        $response = $this->actingAs($user)
                         ->postJson("/cases/{$case->id}/admin-cot", [
                             'action' => 'prelim',
                             'alat' => [
                                 ['nama' => 'Mata Pisau Bedah', 'harga' => 50000],
                                 ['nama' => 'Benang Nilon', 'harga' => 25000]
                             ]
                         ]);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $case->refresh();
        $this->assertTrue((bool)$case->adminCot->prelim_done);
        $this->assertCount(2, $case->alat);
    }
}

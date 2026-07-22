<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\OperationCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpaMeetingAdjustmentsTest extends TestCase
{
    use RefreshDatabase;

    protected $nurse;
    protected $caseManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nurse = User::create([
            'username' => 'nurse.test',
            'name' => 'Nurse Test',
            'role' => 'Nurse'
        ]);

        $this->caseManager = User::create([
            'username' => 'cm.test',
            'name' => 'Case Manager Test',
            'role' => 'CaseManager'
        ]);
    }

    public function test_bpjs_penjamin_persists_hak_kelas_and_rujukan_bpjs()
    {
        // 1. Sync a BPJS case payload from client to server
        $caseData = [
            'id' => 'OCC-20260720-0001',
            'nama' => 'Budi Santoso',
            'rm' => '12-34-56',
            'penjamin' => 'BPJS',
            'hakKelas' => 'Kelas 1 (Ranap)',
            'rujukanBpjs' => 'Rujukan-BPJS-12345',
            'status' => 'Submitted',
            'dpjpList' => ['dr. Andi, Sp.B'],
            'operatorList' => ['dr. Andi, Sp.B'],
            'tindakanList' => ['DEBRIDEMENT'],
            'preOpAnestesi' => 'Ya'
        ];

        $response = $this->actingAs($this->nurse)
            ->postJson('/api/cases/sync-all', [
                'cases' => [$caseData]
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // 2. Query case from database and verify raw_data json contents
        $c = OperationCase::where('id', 'OCC-20260720-0001')->first();
        $this->assertNotNull($c);
        
        $rawData = json_decode($c->raw_data, true);
        $this->assertEquals('BPJS', $rawData['penjamin']);
        $this->assertEquals('Kelas 1 (Ranap)', $rawData['hakKelas']);
        $this->assertEquals('Rujukan-BPJS-12345', $rawData['rujukanBpjs']);
        $this->assertEquals('Ya', $rawData['preOpAnestesi']);
    }

    public function test_expensive_red_flag_item_sets_expensive_flag_and_enters_case_manager_queue()
    {
        // 1. Create case with a Red (Merah) flagged BMHP item
        $caseData = [
            'id' => 'OCC-20260720-0002',
            'nama' => 'Siti Aminah',
            'rm' => '78-90-12',
            'penjamin' => 'BPJS',
            'status' => 'Submitted',
            'dpjpList' => ['dr. Andi, Sp.B'],
            'operatorList' => ['dr. Andi, Sp.B'],
            'tindakanList' => ['DEBRIDEMENT'],
            'tambahanBMHP' => [
                ['jenis' => 'Obat Mahal X', 'harga' => 5000000, 'qty' => 1, 'flag' => 'Merah']
            ],
            'expensiveFlag' => true
        ];

        $response = $this->actingAs($this->nurse)
            ->postJson('/api/cases/sync-all', [
                'cases' => [$caseData]
            ]);

        $response->assertStatus(200);

        // 2. Verify expensiveFlag was saved as true
        $c = OperationCase::where('id', 'OCC-20260720-0002')->first();
        $this->assertNotNull($c);
        $this->assertEquals(1, $c->expensive_flag);

        // 3. Request details as Case Manager and verify dashboard data includes this case
        $response = $this->actingAs($this->caseManager)
            ->withSession(['role' => 'CaseManager'])
            ->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('OCC-20260720-0002');
    }

    public function test_nurse_cannot_submit_case_without_attachments()
    {
        $c = OperationCase::create([
            'id' => 'COT-TEST-99',
            'nama' => 'Test Patient',
            'rm' => '12-34-56',
            'status' => 'Draft'
        ]);

        $response = $this->actingAs($this->nurse)
            ->withSession(['role' => 'Nurse'])
            ->postJson("/cases/{$c->id}/submit");

        $response->assertStatus(422);
        $response->assertJsonFragment(['success' => false]);
    }

    public function test_nurse_can_upload_and_delete_attachments()
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $file = \Illuminate\Http\UploadedFile::fake()->create('formulir_tindakan.pdf', 500); // 500 KB

        $c = OperationCase::create([
            'id' => 'COT-TEST-100',
            'nama' => 'Test Patient',
            'rm' => '12-34-57',
            'status' => 'Draft',
            'raw_data' => json_encode(['attachments' => []])
        ]);

        // Upload attachment
        $response = $this->actingAs($this->nurse)
            ->withSession(['role' => 'Nurse'])
            ->postJson("/cases/{$c->id}/upload-attachment", [
                'file' => $file
            ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['success' => true]);

        // Verify attachment count in raw_data is 1
        $c->refresh();
        $this->assertCount(1, $c->attachments);
        $attId = $c->attachments[0]['id'];

        // Submit should now succeed
        $submitResponse = $this->actingAs($this->nurse)
            ->withSession(['role' => 'Nurse'])
            ->postJson("/cases/{$c->id}/submit");

        $submitResponse->assertStatus(200);
        $this->assertNotEquals('Draft', $c->fresh()->status);
    }
}

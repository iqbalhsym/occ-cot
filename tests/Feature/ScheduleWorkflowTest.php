<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\OperationCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected $adminCot;
    protected $nurse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminCot = User::create([
            'username' => 'admin.cot',
            'name' => 'Admin COT',
            'role' => 'AdminCOT'
        ]);

        $this->nurse = User::create([
            'username' => 'nurse.test',
            'name' => 'Nurse Test',
            'role' => 'Nurse'
        ]);
    }

    public function test_schedule_page_displays_cases()
    {
        $case = OperationCase::create([
            'id' => 'COT-2026-0001',
            'nama' => 'John Doe',
            'rm' => '11-22-33',
            'status' => 'Submitted',
            'raw_data' => json_encode([])
        ]);

        $adminCotRel = $case->adminCot()->create([
            'required' => true,
            'final_done' => true,
            'tanggal_fix' => '2026-07-21',
            'jam_fix' => '08:00',
            'kamar_operasi' => 'OT 1'
        ]);

        $response = $this->actingAs($this->adminCot)
            ->withSession(['role' => 'AdminCOT'])
            ->get('/schedule');

        $response->assertStatus(200);
        $response->assertSee('John Doe');
    }

    public function test_admin_cot_can_drag_reschedule()
    {
        $case = OperationCase::create([
            'id' => 'COT-2026-0002',
            'nama' => 'Jane Doe',
            'rm' => '11-22-34',
            'status' => 'Submitted',
            'raw_data' => json_encode([])
        ]);

        $case->adminCot()->create([
            'required' => true,
            'final_done' => true,
            'tanggal_fix' => '2026-07-21',
            'jam_fix' => '08:00',
            'kamar_operasi' => 'OT 1'
        ]);

        $response = $this->actingAs($this->adminCot)
            ->withSession(['role' => 'AdminCOT'])
            ->postJson("/schedule/drag-reschedule/{$case->id}", [
                'tanggal' => '2026-07-22',
                'jam' => '09:30',
                'ruang' => 'OT 2'
            ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['success' => true]);

        $adminCot = $case->fresh()->adminCot;
        $this->assertEquals('2026-07-22', $adminCot->tanggal_fix->format('Y-m-d'));
        $this->assertEquals('09:30', $adminCot->jam_fix);
        $this->assertEquals('OT 2', $adminCot->kamar_operasi);
    }

    public function test_non_admin_cot_cannot_drag_reschedule()
    {
        $case = OperationCase::create([
            'id' => 'COT-2026-0003',
            'nama' => 'Bob Smith',
            'rm' => '11-22-35',
            'status' => 'Submitted'
        ]);

        $case->adminCot()->create([
            'required' => true,
            'final_done' => true,
            'tanggal_fix' => '2026-07-21',
            'jam_fix' => '08:00',
            'kamar_operasi' => 'OT 1'
        ]);

        $response = $this->actingAs($this->nurse)
            ->withSession(['role' => 'Nurse'])
            ->postJson("/schedule/drag-reschedule/{$case->id}", [
                'tanggal' => '2026-07-22',
                'jam' => '09:30',
                'ruang' => 'OT 2'
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_cot_can_mark_tindakan_selesai()
    {
        $case = OperationCase::create([
            'id' => 'COT-2026-0004',
            'nama' => 'Alice Cooper',
            'rm' => '11-22-36',
            'status' => 'Submitted'
        ]);

        $case->adminCot()->create([
            'required' => true,
            'final_done' => true,
            'tanggal_fix' => '2026-07-21',
            'jam_fix' => '08:00',
            'kamar_operasi' => 'OT 1'
        ]);

        $response = $this->actingAs($this->adminCot)
            ->withSession(['role' => 'AdminCOT'])
            ->postJson("/schedule/tindakan-selesai/{$case->id}");

        $response->assertStatus(200);
        $data = json_decode($case->fresh()->raw_data, true);
        $this->assertTrue($data['adminCot']['tindakanSelesai'] ?? false);
    }

    public function test_admin_cot_can_save_settings()
    {
        $response = $this->actingAs($this->adminCot)
            ->withSession(['role' => 'AdminCOT'])
            ->postJson('/schedule/settings/save', [
                'totMinutes' => 60,
                'slotConfigs' => [
                    ['id' => 'cfg_1', 'ruang' => 'OT 3', 'tanggalMulai' => '2026-07-21', 'jamMulai' => '07:00', 'jamSelesai' => '21:00', 'status' => 'Prioritas Spesialis', 'alat' => ['C-Arm']]
                ]
            ]);

        $response->assertStatus(200);
        $this->assertEquals(60, (int)\App\Models\PrototypeSetting::where('key', 'totMinutes')->first()->value);
    }
}

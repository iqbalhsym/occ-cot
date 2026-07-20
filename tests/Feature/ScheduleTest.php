<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\OperationCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_schedule_page()
    {
        // 1. Create a user
        $user = User::create([
            'username' => 'nurse.test',
            'name' => 'Nurse Test',
            'role' => 'Nurse'
        ]);

        // 2. Create a completed operation case
        $case = OperationCase::create([
            'id' => 'COT-202607-123',
            'nama' => 'Joko Susilo',
            'rm' => '123456',
            'penjamin' => 'Umum',
            'status' => 'Completed',
            'current_flow' => 'Selesai',
            'lokasi_tindakan' => 'COT',
            'tanggal_pilihan1' => now()->addDays(2),
            'jam_operasi' => '09:00',
            'estimasi_rawat_inap' => 3
        ]);

        // 3. Request route
        $response = $this->actingAs($user)
            ->withSession(['role' => 'Nurse'])
            ->get('/schedule');

        $response->assertStatus(200);
        $response->assertSee('Joko Susilo');
        $response->assertSee('123456');
    }

    public function test_guest_cannot_access_schedule_page()
    {
        $response = $this->get('/schedule');
        $response->assertRedirect('/login');
    }
}

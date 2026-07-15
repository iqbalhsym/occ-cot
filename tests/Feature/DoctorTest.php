<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Doctor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_doctors_list()
    {
        $admin = User::create([
            'username' => 'admin.test',
            'name' => 'Admin Test',
            'role' => 'Administrator',
            'password' => bcrypt('password')
        ]);
        
        Doctor::create([
            'nama' => 'Test Doctor',
            'nama_gelar' => 'dr. Test Doctor, Sp.A',
            'ksm' => 'Klinik Anak'
        ]);

        $response = $this->actingAs($admin)
                         ->get('/admin/doctors');

        $response->assertStatus(200);
        $response->assertSee('Test Doctor');
    }

    public function test_non_admin_cannot_view_doctors_list()
    {
        $nurse = User::create([
            'username' => 'nurse.test',
            'name' => 'Nurse Test',
            'role' => 'Nurse',
            'password' => bcrypt('password')
        ]);

        $response = $this->actingAs($nurse)
                         ->get('/admin/doctors');

        $this->assertTrue(in_array($response->status(), [302, 403]));
    }

    public function test_admin_can_create_doctor()
    {
        $admin = User::create([
            'username' => 'admin.test',
            'name' => 'Admin Test',
            'role' => 'SuperAdmin',
            'password' => bcrypt('password')
        ]);

        $response = $this->actingAs($admin)
                         ->postJson('/admin/doctors', [
                             'nama' => 'New Doc',
                             'nama_gelar' => 'dr. New Doc, Sp.OG',
                             'ksm' => 'Klinik Kandungan'
                         ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('doctors', ['nama' => 'New Doc']);
    }

    public function test_admin_can_update_doctor()
    {
        $admin = User::create([
            'username' => 'admin.test',
            'name' => 'Admin Test',
            'role' => 'SuperAdmin',
            'password' => bcrypt('password')
        ]);

        $doctor = Doctor::create([
            'nama' => 'Old Doc',
            'nama_gelar' => 'dr. Old Doc, Sp.A',
            'ksm' => 'Anak'
        ]);

        $response = $this->actingAs($admin)
                         ->putJson("/admin/doctors/{$doctor->id}", [
                             'nama' => 'Updated Doc',
                             'nama_gelar' => 'dr. Updated Doc, Sp.A',
                             'ksm' => 'Anak Baru'
                         ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('doctors', ['nama' => 'Updated Doc']);
    }

    public function test_admin_can_delete_doctor()
    {
        $admin = User::create([
            'username' => 'admin.test',
            'name' => 'Admin Test',
            'role' => 'SuperAdmin',
            'password' => bcrypt('password')
        ]);

        $doctor = Doctor::create([
            'nama' => 'To Delete',
            'nama_gelar' => 'dr. To Delete, Sp.OG',
        ]);

        $response = $this->actingAs($admin)
                         ->deleteJson("/admin/doctors/{$doctor->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('doctors', ['nama' => 'To Delete']);
    }
}

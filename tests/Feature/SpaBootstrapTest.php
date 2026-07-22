<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Pasien;
use App\Models\Doctor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpaBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_spa_bootstraps_patient_and_doctor_records()
    {
        // 1. Create a user
        $user = User::create([
            'username' => 'nurse.test',
            'name' => 'Nurse Test',
            'role' => 'Nurse'
        ]);

        // 2. Create a patient in the database
        Pasien::create([
            'rm' => '12-34-56',
            'nama' => 'Budi Santoso',
            'jenis_kelamin' => 'L',
            'tgl_lahir' => '1985-05-15'
        ]);

        // 3. Create a doctor in the database
        Doctor::create([
            'nama' => 'Andi',
            'nama_gelar' => 'dr. Andi, Sp.B',
            'spesialis' => 'Bedah'
        ]);

        // 4. Request the dynamic patient lookup endpoint
        $responsePatient = $this->actingAs($user)
            ->withSession(['role' => 'Nurse'])
            ->get('/api/patients/12-34-56');

        $responsePatient->assertStatus(200);
        $responsePatient->assertJsonFragment([
            'nama' => 'Budi Santoso',
            'jenis_kelamin' => 'L'
        ]);

        // 5. Request the master-data endpoint for doctors autocomplete
        $responseMaster = $this->actingAs($user)
            ->withSession(['role' => 'Nurse'])
            ->get('/api/master-data');

        $responseMaster->assertStatus(200);
        $responseMaster->assertJsonFragment([
            'nama' => 'Andi',
            'nama_gelar' => 'dr. Andi, Sp.B'
        ]);
    }
}

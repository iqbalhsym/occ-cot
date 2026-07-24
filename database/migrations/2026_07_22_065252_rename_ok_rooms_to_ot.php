<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $mappings = [
            'OK 1' => 'OT 1',
            'OK 2' => 'OT 2',
            'OK 3' => 'OT 3',
            'OK 4' => 'OT 4',
            'OK 5' => 'OT 5',
            'OK 6' => 'OT 6',
            'HYBRID' => 'Hybrid',
            'COT LT 5' => 'OT lt 5',
            'CATHLAB' => 'Cathlab',
        ];

        foreach ($mappings as $old => $new) {
            \DB::table('case_admin_cot')->where('kamar_operasi', $old)->update(['kamar_operasi' => $new]);
            
            $settings = \DB::table('prototype_settings')->where('key', 'slotConfigs')->get();
            foreach ($settings as $s) {
                $val = json_decode($s->value, true);
                if (is_array($val)) {
                    $changed = false;
                    foreach ($val as &$cfg) {
                        if (isset($cfg['ruang']) && $cfg['ruang'] === $old) {
                            $cfg['ruang'] = $new;
                            $changed = true;
                        }
                    }
                    if ($changed) {
                        \DB::table('prototype_settings')->where('id', $s->id)->update(['value' => json_encode($val)]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        $mappings = [
            'OT 1' => 'OK 1',
            'OT 2' => 'OK 2',
            'OT 3' => 'OK 3',
            'OT 4' => 'OK 4',
            'OT 5' => 'OK 5',
            'OT 6' => 'OK 6',
            'Hybrid' => 'HYBRID',
            'OT lt 5' => 'COT LT 5',
            'Cathlab' => 'CATHLAB',
        ];

        foreach ($mappings as $new => $old) {
            \DB::table('case_admin_cot')->where('kamar_operasi', $new)->update(['kamar_operasi' => $old]);
            
            $settings = \DB::table('prototype_settings')->where('key', 'slotConfigs')->get();
            foreach ($settings as $s) {
                $val = json_decode($s->value, true);
                if (is_array($val)) {
                    $changed = false;
                    foreach ($val as &$cfg) {
                        if (isset($cfg['ruang']) && $cfg['ruang'] === $new) {
                            $cfg['ruang'] = $old;
                            $changed = true;
                        }
                    }
                    if ($changed) {
                        \DB::table('prototype_settings')->where('id', $s->id)->update(['value' => json_encode($val)]);
                    }
                }
            }
        }
    }
};

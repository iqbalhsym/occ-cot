<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OperationCase;
use App\Models\Doctor;
use App\Services\QiscusWaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendDoctorScheduleReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-doctor-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send automatic WhatsApp reminder notification to doctors for upcoming surgical schedules';

    /**
     * Execute the console command.
     */
    public function handle(QiscusWaService $qiscusService)
    {
        $this->info("Checking upcoming operation schedules for doctor notifications...");
        Log::info("WA Reminder: Running scheduler...");

        // Load cases that have completed final scheduling, are not cancelled/returned, and not yet notified
        $cases = OperationCase::whereNull('wa_sent_at')
            ->whereNotIn('status', ['Cancelled', 'Returned'])
            ->whereHas('adminCot', function($query) {
                $query->where('final_done', true)
                      ->whereNotNull('tanggal_fix');
            })
            ->with(['adminCot', 'operators', 'tindakan'])
            ->get();

        $this->info("Found " . $cases->count() . " pending cases to analyze.");
        $notifiedCount = 0;

        foreach ($cases as $case) {
            $adminCot = $case->adminCot;
            if (!$adminCot || !$adminCot->tanggal_fix) {
                continue;
            }

            // Combine tanggal_fix and jam_fix to parse scheduled time
            $dateStr = $adminCot->tanggal_fix->format('Y-m-d');
            $timeStr = $adminCot->jam_fix ?: '08:00';
            
            try {
                $scheduledAt = Carbon::createFromFormat('Y-m-d H:i', "{$dateStr} {$timeStr}");
            } catch (\Exception $e) {
                Log::warning("WA Reminder: Failed to parse schedule time for Case {$case->id}: {$dateStr} {$timeStr}");
                continue;
            }

            // Check if scheduled time is within the next 24 hours
            $now = Carbon::now();
            $hoursDiff = $now->diffInHours($scheduledAt, false);

            // Notify if the schedule is in the future and less than 24 hours away
            if ($hoursDiff >= 0 && $hoursDiff <= 24) {
                $operatorName = $case->operators->first()?->nama;
                if (!$operatorName) {
                    Log::info("WA Reminder: Case {$case->id} has no operator assigned.");
                    continue;
                }

                // Match doctor by name (case-insensitive search)
                $doctor = Doctor::where('nama', 'like', "%{$operatorName}%")
                    ->whereNotNull('no_hp')
                    ->where('no_hp', '!=', '')
                    ->first();

                if (!$doctor) {
                    Log::info("WA Reminder: Matching doctor with phone number not found for name '{$operatorName}'.");
                    continue;
                }

                $doctorName = $doctor->nama_gelar ?: $doctor->nama;
                $tindakanList = $case->tindakan->pluck('nama')->toArray();
                $tindakanStr = implode(', ', $tindakanList) ?: '-';
                $formattedDate = $scheduledAt->locale('id')->isoFormat('D MMMM YYYY');
                
                $messageText = "Halo {$doctorName},\n\n" .
                               "Mengingatkan jadwal tindakan operasi Anda:\n" .
                               "🏥 Kamar: " . ($adminCot->kamar_operasi ?: '-') . "\n" .
                               "📅 Tanggal: {$formattedDate}\n" .
                               "⏰ Jam: " . ($adminCot->jam_fix ?: '-') . " WIB\n" .
                               "👤 Pasien: " . ($case->nama ?: '-') . " (RM: " . ($case->rm ?: '-') . ")\n" .
                               "🩺 Tindakan: {$tindakanStr}\n\n" .
                               "Terima kasih.";

                $this->info("Sending WA reminder to {$doctorName} ({$doctor->no_hp}) for case {$case->id}...");
                
                // Attempt to send template HSM notification (or fallback to plain text if session exists)
                // Template name: 'dokter_schedule_reminder'
                $result = $qiscusService->sendTemplateNotification(
                    $doctor->no_hp, 
                    'dokter_schedule_reminder', 
                    [$doctorName, $adminCot->kamar_operasi, "{$formattedDate} {$adminCot->jam_fix} WIB", $case->nama, $tindakanStr]
                );

                if (!$result['success']) {
                    // Fallback to sending plain text message
                    $result = $qiscusService->sendPlainMessage($doctor->no_hp, $messageText);
                }

                if ($result['success']) {
                    $case->wa_sent_at = now();
                    $case->save();
                    $notifiedCount++;
                    Log::info("WA Reminder: Sent successfully to {$doctorName} ({$doctor->no_hp}) for case {$case->id}.");
                } else {
                    Log::error("WA Reminder: Failed to send to {$doctorName} ({$doctor->no_hp}). Error: " . $result['message']);
                }
            }
        }

        $this->info("Completed sending {$notifiedCount} reminders.");
        return Command::SUCCESS;
    }
}

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\OperationCase;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:cleanup-cases', function () {
    $count = OperationCase::where('created_at', '<', now()->subMonths(6))->delete();
    $this->info("Successfully deleted {$count} cases older than 6 months.");
})->purpose('Clean up cases older than 6 months');

Schedule::command('app:cleanup-cases')->daily();

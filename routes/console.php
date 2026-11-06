<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// La auditoría es append-only, pero no debe crecer sin límite en producción.
Schedule::command('audit:prune')
    ->dailyAt('03:00')
    ->withoutOverlapping();

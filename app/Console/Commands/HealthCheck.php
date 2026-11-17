<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Health\HealthChecker;
use Illuminate\Console\Command;

final class HealthCheck extends Command
{
    protected $signature = 'health:check';

    protected $description = 'Comprueba base de datos y caché para los healthchecks de Docker';

    public function handle(HealthChecker $health): int
    {
        $checks = $health->check();

        foreach ($checks as $component => $ok) {
            $this->line(sprintf('%s: %s', $component, $ok ? 'ok' : 'fail'));
        }

        return $health->passes($checks) ? self::SUCCESS : self::FAILURE;
    }
}

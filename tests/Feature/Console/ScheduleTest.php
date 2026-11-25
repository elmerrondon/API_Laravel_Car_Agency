<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Arr;

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
|
| El contenedor `scheduler` ejecuta `schedule:work`; si una tarea desaparece de
| `routes/console.php`, la tabla de auditoría o la de tokens crecerían sin
| límite y nadie se enteraría. Este test fija las dos purgas y su horario.
|
*/

it('programa la purga de auditoría y de tokens caducados', function () {
    $events = app(Schedule::class)->events();

    $audit = Arr::first(
        $events,
        fn (Event $event): bool => str_contains((string) $event->command, 'audit:prune'),
    );

    $tokens = Arr::first(
        $events,
        fn (Event $event): bool => str_contains((string) $event->command, 'sanctum:prune-expired --hours=24'),
    );

    expect($audit)->toBeInstanceOf(Event::class)
        ->and($tokens)->toBeInstanceOf(Event::class)
        ->and($audit?->expression)->toBe('0 3 * * *')
        ->and($tokens?->expression)->toBe('15 3 * * *');
});

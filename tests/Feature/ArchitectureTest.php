<?php

declare(strict_types=1);

use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Arquitectura
|--------------------------------------------------------------------------
|
| Reglas estructurales que no dependen de una ruta concreta: tipado estricto,
| dirección de las dependencias entre capas, DTOs inmutables y ausencia de
| queries en la capa HTTP. Si alguna falla, el mensaje apunta al archivo que
| rompe la convención.
|
*/

arch('todo el código de la aplicación declara strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('el código de la aplicación no deja helpers de depuración')
    ->expect('App')
    ->not->toUse(['dd', 'dump', 'var_dump', 'print_r', 'ray']);

arch('env() solo se usa dentro de config/')
    ->expect('App')
    ->not->toUse('env');

arch('los enums son string-backed')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();

arch('los modelos extienden Eloquent Model')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model');

arch('los DTOs son readonly')
    ->expect('App\DTOs')
    ->toBeReadonly()
    ->ignoring('App\DTOs\Concerns');

arch('los DTOs no dependen de servicios ni de la capa http')
    ->expect('App\DTOs')
    ->not->toUse(['App\Http', 'App\Services'])
    ->ignoring('App\DTOs\Concerns');

arch('la capa de servicios no depende de la capa http')
    ->expect('App\Services')
    ->not->toUse('App\Http');

arch('los modelos no dependen de capas superiores')
    ->expect('App\Models')
    ->not->toUse(['App\Http', 'App\Services']);

arch('los controllers no ejecutan queries por su cuenta')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        'Illuminate\Support\Facades\DB',
        'Illuminate\Database\Eloquent\Builder',
        'Illuminate\Database\Query\Builder',
    ]);

arch('los resources no ejecutan queries')
    ->expect('App\Http\Resources')
    ->not->toUse([
        'Illuminate\Support\Facades\DB',
        'Illuminate\Database\Eloquent\Builder',
        'Illuminate\Database\Query\Builder',
    ]);

/*
| `toUse()` es una comprobación por capa: bastaría con que un controller
| dependiera de `App\Services` para pasar, así que la delegación obligatoria de
| cada controller se comprueba uno a uno.
*/
it('hace que cada controller delegue en un servicio', function () {
    $controllers = collect(File::allFiles(app_path('Http/Controllers')))
        ->map(fn (SplFileInfo $file): string => 'App\Http\Controllers\\'.Str::of($file->getRelativePathname())
            ->replace(['/', '.php'], ['\\', ''])
            ->toString())
        ->reject(fn (string $class): bool => $class === 'App\Http\Controllers\Controller')
        ->values();

    expect($controllers)->not->toBeEmpty();

    foreach ($controllers as $controller) {
        $dependencies = collect((new ReflectionClass($controller))->getConstructor()?->getParameters() ?? [])
            ->map(fn (ReflectionParameter $parameter): ?string => $parameter->getType()?->getName());

        $injectsService = $dependencies->contains(
            fn (?string $type): bool => $type !== null && str_starts_with($type, 'App\Services')
        );

        expect($injectsService)->toBeTrue("El controller [{$controller}] no inyecta ningún servicio.");
    }
});

/*
| La auditoría es obligatoria en toda escritura. El test detecta servicios con
| métodos públicos `create*`, `update*` o `delete*` y exige que inyecten
| `AuditService`; la lista blanca solo admite servicios de solo lectura que
| usen esos nombres sin persistir nada (hoy está vacía).
*/
it('hace que todo servicio de escritura use AuditService', function () {
    // Temporal: servicios pendientes de auditar en 7.3. Debe quedar vacía.
    $pendingAudit = [
        'App\Services\Locations\CountryService',
        'App\Services\Locations\StateService',
        'App\Services\Locations\CityService',
    ];

    $services = collect(File::allFiles(app_path('Services')))
        ->map(fn (SplFileInfo $file): string => 'App\Services\\'.Str::of($file->getRelativePathname())
            ->replace(['/', '.php'], ['\\', ''])
            ->toString())
        ->reject(fn (string $class): bool => $class === AuditService::class)
        ->values();

    $offenders = $services
        ->reject(fn (string $class): bool => in_array($class, $pendingAudit, true))
        ->filter(function (string $class): bool {
            $reflection = new ReflectionClass($class);

            $writes = collect($reflection->getMethods(ReflectionMethod::IS_PUBLIC))
                ->contains(fn (ReflectionMethod $method): bool => preg_match('/^(create|update|delete)/i', $method->getName()) === 1);

            if (! $writes) {
                return false;
            }

            return ! collect($reflection->getConstructor()?->getParameters() ?? [])
                ->contains(fn (ReflectionParameter $parameter): bool => $parameter->getType()?->getName() === AuditService::class);
        })
        ->values();

    expect($offenders)->toBeEmpty(
        'Estos servicios escriben sin auditar: '.$offenders->implode(', ')
    );
});

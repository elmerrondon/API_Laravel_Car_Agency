<?php

declare(strict_types=1);

use App\Models\Cars\Car;
use App\Models\Cars\Color;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('activa la detección de lazy loading fuera de producción', function () {
    expect(app()->isProduction())->toBeFalse()
        ->and(Model::preventsLazyLoading())->toBeTrue();
});

it('rompe la petición cuando una colección hidratada carga una relación perezosa', function () {
    $cars = Car::query()->take(2)->get();

    expect(fn () => $cars[0]->color)->toThrow(LazyLoadingViolationException::class);
});

it('permite la relación cuando viene con eager loading', function () {
    $cars = Car::with('color')->take(2)->get();

    expect($cars[0]->color)->toBeInstanceOf(Color::class)
        ->and($cars[1]->color)->toBeInstanceOf(Color::class);
});

it('deja pasar las relaciones de un modelo cargado de uno en uno', function () {
    // El framework solo marca los modelos hidratados en colecciones, así que
    // `first()` no rompe; el guard real son los listados (ver QueryCountTest).
    $car = Car::query()->firstOrFail();

    expect($car->color)->toBeInstanceOf(Color::class);
});

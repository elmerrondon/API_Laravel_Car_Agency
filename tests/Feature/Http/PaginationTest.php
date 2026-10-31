<?php

declare(strict_types=1);

use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

/**
 * Comprueba que las páginas mantienen un orden estable por id y que los
 * enlaces de paginación conservan `per_page`.
 */
function assertPaginationContract(string $url): void
{
    $first = test()->getJson("{$url}?per_page=2&page=1")->assertOk();
    $second = test()->getJson("{$url}?per_page=2&page=2")->assertOk();

    /** @var list<int> $firstIds */
    $firstIds = array_column($first->json('data'), 'id');
    /** @var list<int> $secondIds */
    $secondIds = array_column($second->json('data'), 'id');

    $linkUrls = collect($first->json('meta.links'))->pluck('url')->filter();

    expect($firstIds)->toHaveCount(2)
        ->and($firstIds[0])->toBeLessThan($firstIds[1])
        ->and($secondIds)->not->toBeEmpty()
        ->and($firstIds[1])->toBeLessThan($secondIds[0])
        ->and($first->json('links.next'))->toContain('per_page=2')
        ->and($linkUrls)->not->toBeEmpty();

    $linkUrls->each(fn (string $link) => expect($link)->toContain('per_page=2'));
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    Sanctum::actingAs(User::where('email', 'admin@caragency.test')->firstOrFail());
});

it('pagina los listados con orden estable y conserva los filtros en los enlaces', function (string $url) {
    assertPaginationContract($url);
})->with([
    '/api/cars',
    '/api/brands',
    '/api/colors',
    '/api/categories',
    '/api/car-types',
    '/api/car-models',
    '/api/taxes',
    '/api/currencies',
    '/api/payment-methods',
    '/api/branches',
    '/api/cities',
    '/api/states',
    '/api/countries',
    '/api/users',
    '/api/roles',
]);

it('pagina las ventas con orden estable y conserva los filtros en los enlaces', function () {
    seedSales(3);

    assertPaginationContract('/api/sales');
});

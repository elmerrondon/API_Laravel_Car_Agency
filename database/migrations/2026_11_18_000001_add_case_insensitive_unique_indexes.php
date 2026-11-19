<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unicidad case-insensitive de los catálogos y de los usuarios.
     *
     * MySQL comparaba los `utf8mb4_unicode_ci` sin distinguir mayúsculas; en
     * PostgreSQL el cotejo `C` es binario, así que `Sedán` y `sedán` serían
     * dos filas distintas. La unicidad se mueve de la restricción plana a un
     * índice de expresión sobre `lower(...)`, que además es el índice que
     * aprovechan las búsquedas case-insensitive (`whereRaw('lower(name) = ?')`).
     *
     * Los índices de los usuarios son parciales (`WHERE deleted_at IS NULL`)
     * porque el par `(email, deleted_at)` solo protege las filas activas en
     * MySQL: en PostgreSQL los `NULL` de `deleted_at` son distintos entre sí y
     * dos usuarios activos podrían compartir email o documento.
     *
     * `currencies.code`, `taxes.code`, `branches.code`, `users.code` y
     * `cars.vin` conservan su índice plano: son códigos que la API normaliza
     * en el borde antes de escribirlos.
     *
     * @var array<string, array{0: string, 1: string, 2: list<string>, 3: string}>
     */
    private const INDEXES = [
        'countries' => ['countries_name_lower_unique', 'countries_name_unique', ['name'], 'lower(name)'],
        'states' => ['states_name_country_lower_unique', 'states_name_country_id_unique', ['name', 'country_id'], 'lower(name), country_id'],
        'cities' => ['cities_name_state_lower_unique', 'cities_name_state_id_unique', ['name', 'state_id'], 'lower(name), state_id'],
        'branches' => ['branches_name_lower_unique', 'branches_name_unique', ['name'], 'lower(name)'],
        'brands' => ['brands_name_lower_unique', 'brands_name_unique', ['name'], 'lower(name)'],
        'car_models' => ['car_models_name_lower_unique', 'car_models_name_unique', ['name'], 'lower(name)'],
        'car_types' => ['car_types_name_lower_unique', 'car_types_name_unique', ['name'], 'lower(name)'],
        'categories' => ['categories_name_lower_unique', 'categories_name_unique', ['name'], 'lower(name)'],
        'colors' => ['colors_name_lower_unique', 'colors_name_unique', ['name'], 'lower(name)'],
        'payment_methods' => ['payment_methods_name_lower_unique', 'payment_methods_name_unique', ['name'], 'lower(name)'],
        'currencies' => ['currencies_name_lower_unique', 'currencies_name_unique', ['name'], 'lower(name)'],
        'taxes' => ['taxes_name_lower_unique', 'taxes_name_unique', ['name'], 'lower(name)'],
        'roles' => ['roles_name_lower_unique', 'roles_name_unique', ['name'], 'lower(name)'],
        'permissions' => ['permissions_name_lower_unique', 'permissions_name_unique', ['name'], 'lower(name)'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // La restricción hay que soltarla con `alter table ... drop constraint`
        // (lo hace el schema builder); `drop index` falla en PostgreSQL porque
        // el índice pertenece a la restricción.
        foreach (self::INDEXES as $table => [, $previous]) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropUnique($previous));
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_deleted_at_unique');
            $table->dropUnique('users_document_number_deleted_at_unique');
        });

        foreach (self::INDEXES as $table => [$index, , , $expression]) {
            DB::statement(sprintf('create unique index %s on %s (%s)', $index, $table, $expression));
        }

        DB::statement('create unique index users_email_lower_unique on users (lower(email)) where deleted_at is null');
        DB::statement(
            'create unique index users_document_number_lower_unique on users (lower(document_number)) where deleted_at is null'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('drop index if exists users_document_number_lower_unique');
        DB::statement('drop index if exists users_email_lower_unique');

        foreach (self::INDEXES as [$index]) {
            DB::statement(sprintf('drop index if exists %s', $index));
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique(['email', 'deleted_at']);
            $table->unique(['document_number', 'deleted_at']);
        });

        foreach (self::INDEXES as $table => [$index, $previous, $columns]) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique($columns, $previous));
        }
    }
};

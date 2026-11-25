<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('crea los índices de consulta previstos', function () {
    expect(Schema::hasIndex('cars', ['status']))->toBeTrue()
        ->and(Schema::hasIndex('cars', ['branch_id', 'status']))->toBeTrue()
        ->and(Schema::hasIndex('sales', ['sale_date']))->toBeTrue()
        ->and(Schema::hasIndex('sales', ['status', 'sale_date']))->toBeTrue()
        ->and(Schema::hasIndex('sales', ['branch_id', 'sale_date']))->toBeTrue()
        ->and(Schema::hasIndex('sales', ['branch_id', 'status', 'sale_date']))->toBeTrue()
        ->and(Schema::hasIndex('users', ['is_active']))->toBeTrue();
});

it('indexa las claves foráneas que postgres no indexa', function (string $table, string $index) {
    expect(Schema::hasIndex($table, $index))->toBeTrue();
})->with([
    'states.country_id' => ['states', 'states_country_id_index'],
    'cities.state_id' => ['cities', 'cities_state_id_index'],
    'branches.city_id' => ['branches', 'branches_city_id_index'],
    'brands.country_id' => ['brands', 'brands_country_id_index'],
    'car_models.brand_id' => ['car_models', 'car_models_brand_id_index'],
    'cars.color_id' => ['cars', 'cars_color_id_index'],
    'cars.car_model_id' => ['cars', 'cars_car_model_id_index'],
    'cars.car_type_id' => ['cars', 'cars_car_type_id_index'],
    'cars.category_id' => ['cars', 'cars_category_id_index'],
    'role_user.user_id' => ['role_user', 'role_user_user_id_index'],
    'permission_role.role_id' => ['permission_role', 'permission_role_role_id_index'],
    'branch_user.user_id' => ['branch_user', 'branch_user_user_id_index'],
    'sales.car_id' => ['sales', 'sales_car_id_index'],
    'sales.user_id' => ['sales', 'sales_user_id_index'],
    'sales.payment_method_id' => ['sales', 'sales_payment_method_id_index'],
    'sales.payment_currency_id' => ['sales', 'sales_payment_currency_id_index'],
    'sales.base_currency_id' => ['sales', 'sales_base_currency_id_index'],
]);

it('no repite las columnas que ya encabezan un índice compuesto', function (string $table, string $index) {
    expect(Schema::hasIndex($table, $index))->toBeFalse();
})->with([
    'cars.branch_id' => ['cars', 'cars_branch_id_index'],
    'sales.branch_id' => ['sales', 'sales_branch_id_index'],
    'audit_logs.user_id' => ['audit_logs', 'audit_logs_user_id_index'],
    'role_user.role_id' => ['role_user', 'role_user_role_id_index'],
    'permission_role.permission_id' => ['permission_role', 'permission_role_permission_id_index'],
    'branch_user.branch_id' => ['branch_user', 'branch_user_branch_id_index'],
]);

it('usa índices case-insensitive en los catálogos', function (string $table, string $index) {
    expect(Schema::hasIndex($table, $index))->toBeTrue();
})->with([
    'countries.name' => ['countries', 'countries_name_lower_unique'],
    'states.name' => ['states', 'states_name_country_lower_unique'],
    'cities.name' => ['cities', 'cities_name_state_lower_unique'],
    'branches.name' => ['branches', 'branches_name_lower_unique'],
    'brands.name' => ['brands', 'brands_name_lower_unique'],
    'car_models.name' => ['car_models', 'car_models_name_lower_unique'],
    'car_types.name' => ['car_types', 'car_types_name_lower_unique'],
    'categories.name' => ['categories', 'categories_name_lower_unique'],
    'colors.name' => ['colors', 'colors_name_lower_unique'],
    'payment_methods.name' => ['payment_methods', 'payment_methods_name_lower_unique'],
    'currencies.name' => ['currencies', 'currencies_name_lower_unique'],
    'taxes.name' => ['taxes', 'taxes_name_lower_unique'],
    'roles.name' => ['roles', 'roles_name_lower_unique'],
    'permissions.name' => ['permissions', 'permissions_name_lower_unique'],
]);

it('reemplaza la unicidad plana de los catálogos en lugar de duplicarla', function (string $table, string $index) {
    expect(Schema::hasIndex($table, $index))->toBeFalse();
})->with([
    'countries.name' => ['countries', 'countries_name_unique'],
    'states.name' => ['states', 'states_name_country_id_unique'],
    'cities.name' => ['cities', 'cities_name_state_id_unique'],
    'branches.name' => ['branches', 'branches_name_unique'],
    'brands.name' => ['brands', 'brands_name_unique'],
    'car_models.name' => ['car_models', 'car_models_name_unique'],
    'car_types.name' => ['car_types', 'car_types_name_unique'],
    'categories.name' => ['categories', 'categories_name_unique'],
    'colors.name' => ['colors', 'colors_name_unique'],
    'payment_methods.name' => ['payment_methods', 'payment_methods_name_unique'],
    'currencies.name' => ['currencies', 'currencies_name_unique'],
    'taxes.name' => ['taxes', 'taxes_name_unique'],
    'roles.name' => ['roles', 'roles_name_unique'],
    'permissions.name' => ['permissions', 'permissions_name_unique'],
]);

it('protege el email y el documento solo en los usuarios activos', function () {
    expect(Schema::hasIndex('users', 'users_email_lower_unique'))->toBeTrue()
        ->and(Schema::hasIndex('users', 'users_document_number_lower_unique'))->toBeTrue()
        ->and(Schema::hasIndex('users', 'users_email_deleted_at_unique'))->toBeFalse()
        ->and(Schema::hasIndex('users', 'users_document_number_deleted_at_unique'))->toBeFalse();
});

it('conserva la unicidad global de los códigos y del vin', function (string $table, string $index) {
    expect(Schema::hasIndex($table, $index))->toBeTrue();
})->with([
    'cars.vin' => ['cars', 'cars_vin_unique'],
    'users.code' => ['users', 'users_code_unique'],
    'branches.code' => ['branches', 'branches_code_unique'],
    'currencies.code' => ['currencies', 'currencies_code_unique'],
    'taxes.code' => ['taxes', 'taxes_code_unique'],
]);

it('conserva la unicidad global de vin sin índices duplicados', function () {
    expect(Schema::hasIndex('cars', 'cars_vin_unique'))->toBeTrue()
        ->and(Schema::hasIndex('cars', 'deleted_at'))->toBeFalse()
        ->and(Schema::hasIndex('cars', 'cars_vin_deleted_at_unique'))->toBeFalse();
});

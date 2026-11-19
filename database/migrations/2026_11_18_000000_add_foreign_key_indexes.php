<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * MySQL crea un índice por cada clave foránea; PostgreSQL no. Sin estos
     * índices cada `DELETE`/`UPDATE` sobre la tabla padre recorre la hija
     * completa y los listados que filtran por la FK hacen `Seq Scan`. No se
     * repiten las columnas que ya van al frente de un índice compuesto o
     * único: `cars.branch_id` (`branch_id, status`), `sales.branch_id`
     * (`branch_id, sale_date`), `audit_logs.user_id` (`user_id, created_at`),
     * `role_user.role_id`, `permission_role.permission_id` y
     * `branch_user.branch_id`.
     */
    public function up(): void
    {
        Schema::table('states', function (Blueprint $table) {
            $table->index('country_id');
        });

        Schema::table('cities', function (Blueprint $table) {
            // `cities_name_state_id_unique` deja `state_id` al final, así que
            // no sirve para resolver la FK ni el filtro por estado.
            $table->index('state_id');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->index('city_id');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->index('country_id');
        });

        Schema::table('car_models', function (Blueprint $table) {
            $table->index('brand_id');
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->index('color_id');
            $table->index('car_model_id');
            $table->index('car_type_id');
            $table->index('category_id');
        });

        Schema::table('role_user', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('permission_role', function (Blueprint $table) {
            $table->index('role_id');
        });

        Schema::table('branch_user', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->index('car_id');
            $table->index('user_id');
            $table->index('payment_method_id');
            $table->index('payment_currency_id');
            $table->index('base_currency_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['car_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['payment_method_id']);
            $table->dropIndex(['payment_currency_id']);
            $table->dropIndex(['base_currency_id']);
        });

        Schema::table('branch_user', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('permission_role', function (Blueprint $table) {
            $table->dropIndex(['role_id']);
        });

        Schema::table('role_user', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->dropIndex(['color_id']);
            $table->dropIndex(['car_model_id']);
            $table->dropIndex(['car_type_id']);
            $table->dropIndex(['category_id']);
        });

        Schema::table('car_models', function (Blueprint $table) {
            $table->dropIndex(['brand_id']);
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropIndex(['country_id']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropIndex(['city_id']);
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->dropIndex(['state_id']);
        });

        Schema::table('states', function (Blueprint $table) {
            $table->dropIndex(['country_id']);
        });
    }
};

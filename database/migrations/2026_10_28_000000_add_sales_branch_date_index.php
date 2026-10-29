<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Los reportes filtran por sucursal y ordenan por fecha. El índice
            // de la FK (branch_id) obliga a un filesort; este lo evita y sigue
            // sirviendo las consultas que solo filtran por sucursal.
            $table->index(['branch_id', 'sale_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'sale_date']);
        });
    }
};

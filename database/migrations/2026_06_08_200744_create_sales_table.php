<?php

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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->decimal("exchange_rate",10,2);
            $table->decimal("base_price",15,2);
            $table->json("tax_breakdown");
            $table->decimal("total_taxes",15,2);
            $table->decimal("discount",15,2);
            $table->decimal("total_base_amount",15,2);
            $table->decimal("total_amount_paid",15,2);
            $table->string("status",30);
            $table->dateTime("sale_date");
            $table->foreignId("car_id")->constrained()->onDelete("restrict");
            $table->foreignId("user_id")->constrained()->onDelete("restrict");
            $table->foreignId("branch_id")->constrained()->onDelete("restrict");
            $table->foreignId("payment_currency_id")->constrained("currencies")->onDelete("restrict");
            $table->foreignId("base_currency_id")->constrained("currencies")->onDelete("restrict");
            $table->foreignId("payment_method_id")->constrained()->onDelete("restrict");
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};

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
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string("name", 50)->unique();
            $table->string("code", 12)->unique();
            $table->string("address", 255);
            $table->string("description", 300)->nullable();
            $table->string("zip_code", 20);
            $table->boolean("is_active")->default(true);
            $table->foreignId("city_id")->constrained()->onDelete("restrict");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};

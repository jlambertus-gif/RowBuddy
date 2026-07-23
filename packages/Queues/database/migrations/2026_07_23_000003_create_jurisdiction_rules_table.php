<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurisdiction_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('jurisdiction_country', 2);
            $table->string('category')->nullable();
            $table->boolean('permitted');
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->timestamps();

            $table->index(['jurisdiction_country', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurisdiction_rules');
    }
};

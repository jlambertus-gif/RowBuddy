<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restricted_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code');
            $table->string('jurisdiction_country', 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['code', 'jurisdiction_country']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restricted_categories');
    }
};

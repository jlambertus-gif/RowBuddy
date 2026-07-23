<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('category');
            $table->string('jurisdiction_country', 2);
            $table->decimal('center_latitude', 10, 7);
            $table->decimal('center_longitude', 10, 7);
            $table->double('radius_meters');
            $table->string('authorship');
            $table->string('organizer_reference')->nullable();
            $table->string('status');
            $table->timestamps();

            $table->index('status');
            $table->index('jurisdiction_country');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queues');
    }
};

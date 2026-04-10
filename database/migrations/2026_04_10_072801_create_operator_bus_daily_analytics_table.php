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
        Schema::create('operator_bus_daily_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bus_id')->constrained()->onDelete('cascade');
            $table->foreignId('operator_id')->nullable();

            $table->date('date');

            $table->float('total_distance_km')->default(0);
            $table->integer('total_passengers')->default(0);
            $table->float('avg_speed')->default(0);
            $table->integer('location_points')->default(0);

            $table->decimal('last_lat', 10, 7)->nullable();
            $table->decimal('last_lng', 10, 7)->nullable();

            $table->timestamp('started_at')->nullable();

            $table->timestamps();

            $table->unique(['bus_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operator_bus_daily_analytics');
    }
};

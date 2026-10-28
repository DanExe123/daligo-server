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
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('rider_lat', 10, 7)->nullable();
            $table->decimal('rider_lng', 10, 7)->nullable();
            $table->timestamp('rider_located_at')->nullable();
        });
    }
    
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['rider_lat', 'rider_lng', 'rider_located_at']);
        });
    }
};

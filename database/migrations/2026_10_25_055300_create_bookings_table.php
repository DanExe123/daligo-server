<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('rider_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('item_description');

            $table->string('pickup_name');
            $table->text('pickup_address')->nullable();
            $table->decimal('pickup_lat', 10, 7);
            $table->decimal('pickup_lng', 10, 7);

            $table->string('dropoff_name');
            $table->text('dropoff_address')->nullable();
            $table->decimal('dropoff_lat', 10, 7);
            $table->decimal('dropoff_lng', 10, 7);

            $table->decimal('distance_km', 8, 2);
            $table->unsignedInteger('duration_min');
            $table->decimal('fee', 8, 2);
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();

            // pending | accepted | picked_up | completed | cancelled
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
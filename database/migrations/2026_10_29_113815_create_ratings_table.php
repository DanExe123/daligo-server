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
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
 
            // one rating per booking
            $table->foreignId('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();
 
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('rider_id')->constrained('users')->cascadeOnDelete();
 
            $table->unsignedTinyInteger('rating');   // 1 to 5 stars
            $table->text('feedback')->nullable();    // experience / report text
 
            $table->timestamps();
 
            $table->index('rider_id');               // fast rider average
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};

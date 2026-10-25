<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    protected $fillable = [
        'customer_id', 'rider_id', 'item_description',
        'pickup_name', 'pickup_address', 'pickup_lat', 'pickup_lng',
        'dropoff_name', 'dropoff_address', 'dropoff_lat', 'dropoff_lng',
        'distance_km', 'duration_min', 'fee', 'status',
    ];

    protected function casts(): array
    {
        return [
            'pickup_lat' => 'float', 'pickup_lng' => 'float',
            'dropoff_lat' => 'float', 'dropoff_lng' => 'float',
            'distance_km' => 'float', 'fee' => 'float',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }
}
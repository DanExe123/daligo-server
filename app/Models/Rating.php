<?php
 
// ============================================================
// app/Models/Rating.php
// ============================================================
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
 
class Rating extends Model
{
    protected $fillable = [
        'booking_id',
        'customer_id',
        'rider_id',
        'rating',
        'feedback',
    ];
 
    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }
 
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
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
 
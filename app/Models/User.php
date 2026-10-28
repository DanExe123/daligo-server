<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'password', 'role', 'is_online'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_online'         => 'boolean',
        ];
    }

    public function isRider(): bool
    {
        return strtolower((string) $this->role) === 'rider';
    }

    // User::rider()->find($id) -> only users with the rider role
    public function scopeRider(Builder $query): Builder
    {
        return $query->whereRaw('LOWER(role) = ?', ['rider']);
    }

    // Deliveries assigned to this user as the rider
    public function deliveries(): HasMany
    {
        return $this->hasMany(Booking::class, 'rider_id');
    }

    // Bookings this user made as the customer
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'customer_id');
    }
}
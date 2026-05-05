<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'idempotency_key',
        'customer_email',
        'status',
        'subtotal',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'subtotal' => 'decimal:2',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }
}

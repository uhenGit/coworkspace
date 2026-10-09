<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'total' => 'decimal:2',
            'discount' => 'integer',
            'status' => InvoiceStatus::class,
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected $fillable = [
        'booking_id',
        'discount',
        'amount',
        'status',
        'expires_at',
        'total',
    ];
}

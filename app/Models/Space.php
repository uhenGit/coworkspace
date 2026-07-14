<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Space extends Model
{
    use HasFactory;
    use SoftDeletes;

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function bookings(): BelongsTo
    {
        return $this->hasMany(Booking::class);
    }

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
            'price_per_hour' => 'decimal:2',
        ];
    }
}

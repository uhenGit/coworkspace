<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Space extends Model
{
    use SoftDeletes;

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function bookings(): BelongsTo
    {
        return $this->hasMany(Booking::class);
    }
}

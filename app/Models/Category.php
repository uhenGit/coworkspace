<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'booking_buffer_minutes',
    ];

    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class);
    }

    public function getRouteKeyName(): string
    {
        return 'short_code';
    }
}

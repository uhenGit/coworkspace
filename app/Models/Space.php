<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Space extends Model
{
    public function category()
    {
      return $this->belongsTo(Category::class);
    }

    public function bookings()
    {
      return $this->hasMany(Booking::class);
    }
}

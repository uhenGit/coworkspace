<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    public function spaces()
    {
      return $this->hasMany(Space::class);
    }
}

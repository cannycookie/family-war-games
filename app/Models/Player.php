<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    protected $fillable = ['name', 'slug', 'colour', 'position'];

    public function scores(): HasMany
    {
        return $this->hasMany(ResultScore::class);
    }
}

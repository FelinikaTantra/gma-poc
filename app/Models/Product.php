<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = [];

    protected $casts = [
        'vector' => 'array',
    ];

    public function productCompatibilities()
    {
        return $this->hasMany(ProductCompatibility::class);
    }
}

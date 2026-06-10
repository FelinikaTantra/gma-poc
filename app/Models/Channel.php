<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    protected $guarded = [];

    protected $casts = [
        'config_json' => 'array',
    ];

    public function customers() {
        return $this->hasMany(Customer::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $guarded = [];

    public function channel() {
        return $this->belongsTo(Channel::class);
    }

    public function conversations() {
        return $this->hasMany(Conversation::class);
    }
}

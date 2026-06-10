<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $guarded = [];

    public function customer() {
        return $this->belongsTo(Customer::class);
    }

    public function channel() {
        return $this->belongsTo(Channel::class);
    }

    public function messages() {
        return $this->hasMany(Message::class);
    }
}

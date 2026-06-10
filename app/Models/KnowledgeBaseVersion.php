<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeBaseVersion extends Model
{
    protected $guarded = [];

    public function knowledgeBase() {
        return $this->belongsTo(KnowledgeBase::class);
    }

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Requirement extends Model
{
    protected $fillable = ['transaction_type', 'name', 'description'];

    public function type(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class, 'transaction_type');
    }
}

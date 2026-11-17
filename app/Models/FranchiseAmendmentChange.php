<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FranchiseAmendmentChange extends Model
{
    protected $fillable = [
        'franchise_amendment_id',
        'field_name',
        'old_value',
        'new_value',
    ];

    public function amendment(): BelongsTo
    {
        return $this->belongsTo(FranchiseAmendment::class, 'franchise_amendment_id');
    }
}

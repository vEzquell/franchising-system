<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FranchiseAmendment extends Model
{
    protected $fillable = [
        'franchise_transaction_id',
        'amendment_type',
        'reason',
        'remarks',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FranchiseTransaction::class, 'franchise_transaction_id');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(FranchiseAmendmentChange::class, 'franchise_amendment_id');
    }

    public function transfer(): HasOne
    {
        return $this->hasOne(FranchiseTransfer::class, 'franchise_amendment_id');
    }
}

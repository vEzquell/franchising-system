<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FranchiseTransfer extends Model
{
    protected $fillable = [
        'franchise_amendment_id',
        'previous_owner_id',
        'new_owner_id',
        'transfer_date',
    ];

    protected function casts(): array
    {
        return [
            'transfer_date' => 'date',
        ];
    }

    public function amendment(): BelongsTo
    {
        return $this->belongsTo(FranchiseAmendment::class, 'franchise_amendment_id');
    }

    public function previousOwner(): BelongsTo
    {
        return $this->belongsTo(FranchiseOwner::class, 'previous_owner_id');
    }

    public function newOwner(): BelongsTo
    {
        return $this->belongsTo(FranchiseOwner::class, 'new_owner_id');
    }
}

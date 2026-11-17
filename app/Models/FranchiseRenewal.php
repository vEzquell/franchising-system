<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class FranchiseRenewal extends Model
{
    protected $fillable = [
        'franchise_transaction_id',
        'franchise_owner_id',
        'renewal_date',
        'previous_expiration_date',
        'new_expiration_date',
    ];

    protected function casts(): array
    {
        return [
            'renewal_date' => 'date',
            'previous_expiration_date' => 'date',
            'new_expiration_date' => 'date',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FranchiseTransaction::class, 'franchise_transaction_id');
    }

    public function franchise(): HasOneThrough
    {
        return $this->hasOneThrough(
            Franchise::class,
            FranchiseTransaction::class,
            'id',
            'id',
            'franchise_transaction_id',
            'franchise_id',
        );
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(FranchiseOwner::class, 'franchise_owner_id');
    }
}

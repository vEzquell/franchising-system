<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FranchiseTransaction extends Model
{
    protected $fillable = [
        'franchise_id',
        'transaction_type',
        'status',
        'date_filed',
        'approved_at',
        'processed_by',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'date_filed' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class, 'franchise_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function renewal(): HasOne
    {
        return $this->hasOne(FranchiseRenewal::class, 'franchise_transaction_id');
    }

    public function amendment(): HasOne
    {
        return $this->hasOne(FranchiseAmendment::class, 'franchise_transaction_id');
    }

    public function substitution(): HasOne
    {
        return $this->hasOne(FranchiseSubstitution::class, 'franchise_transaction_id');
    }
}

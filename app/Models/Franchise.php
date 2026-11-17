<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Franchise extends Model
{
    protected $fillable = [
        'franchise_number',
        'status',
        'date_issued',
        'expiration_date',
    ];

    protected function casts(): array
    {
        return [
            'date_issued' => 'date',
            'expiration_date' => 'date',
        ];
    }

    public function ownerships(): HasMany
    {
        return $this->hasMany(FranchiseOwnership::class, 'franchise_id');
    }

    public function renewals(): HasManyThrough
    {
        return $this->hasManyThrough(
            FranchiseRenewal::class,
            FranchiseTransaction::class,
            'franchise_id',
            'franchise_transaction_id',
        );
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FranchiseTransaction::class, 'franchise_id');
    }
}

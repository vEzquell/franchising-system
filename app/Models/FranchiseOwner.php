<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FranchiseOwner extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'suffix',
        'middle_name',
        'address',
        'contact_number',
    ];

    public function ownerships(): HasMany
    {
        return $this->hasMany(FranchiseOwnership::class, 'franchise_owner_id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(FranchiseRenewal::class, 'franchise_owner_id');
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(FranchiseTransfer::class, 'previous_owner_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(FranchiseTransfer::class, 'new_owner_id');
    }
}

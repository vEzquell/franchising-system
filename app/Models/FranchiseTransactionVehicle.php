<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FranchiseTransactionVehicle extends Model
{
    protected $fillable = ['franchise_transaction_id', 'vehicle_id', 'franchise_owner_id', 'vehicle_details'];

    protected function casts(): array
    {
        return ['vehicle_details' => 'array'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FranchiseTransaction::class, 'franchise_transaction_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(FranchiseOwner::class, 'franchise_owner_id');
    }
}

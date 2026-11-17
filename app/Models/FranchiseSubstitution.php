<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FranchiseSubstitution extends Model
{
    protected $fillable = [
        'franchise_transaction_id',
        'previous_vehicle_id',
        'new_vehicle_id',
        'substitution_date',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'substitution_date' => 'date',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FranchiseTransaction::class, 'franchise_transaction_id');
    }

    public function previousVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'previous_vehicle_id');
    }

    public function newVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'new_vehicle_id');
    }
}

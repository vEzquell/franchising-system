<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FranchiseVehicleAssignment extends Model
{
    protected $fillable = ['franchise_id', 'vehicle_id', 'started_at', 'ended_at'];

    protected function casts(): array
    {
        return ['started_at' => 'date', 'ended_at' => 'date'];
    }

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $fillable = [
        'make_type',
        'plate_number',
        'motor_number',
        'chassis_number',
    ];

    public function outgoingSubstitutions(): HasMany
    {
        return $this->hasMany(FranchiseSubstitution::class, 'previous_vehicle_id');
    }

    public function incomingSubstitutions(): HasMany
    {
        return $this->hasMany(FranchiseSubstitution::class, 'new_vehicle_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FranchiseOwnership extends Model
{
    protected $fillable = [
        'franchise_id',
        'franchise_owner_id',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'ended_at' => 'date',
        ];
    }

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class, 'franchise_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(FranchiseOwner::class, 'franchise_owner_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }
}

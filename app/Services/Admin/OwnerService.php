<?php

namespace App\Services\Admin;

use App\Models\Franchise;
use App\Models\FranchiseOwner;
use App\Models\FranchiseOwnership;
use App\Models\FranchiseTransfer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OwnerService
{
    public function transfer(Franchise $franchise, FranchiseTransfer $transfer): FranchiseOwnership
    {
        $date = $transfer->transfer_date->toDateString();
        $ownerships = $franchise->ownerships()
            ->where(fn (Builder $query) => $query->whereNull('ended_at')->orWhereDate('ended_at', '>', $date))
            ->lockForUpdate()->get();
        $previous = $ownerships->count() === 1 ? $ownerships->sole() : null;

        if (! $previous || $previous->franchise_owner_id !== $transfer->previous_owner_id || $previous->started_at->toDateString() > $date) {
            throw ValidationException::withMessages(['status' => 'The ownership records no longer allow this transfer on the requested date.']);
        }

        $previous->update(['ended_at' => $date]);
        return $franchise->ownerships()->create(['franchise_owner_id' => $transfer->new_owner_id, 'started_at' => $date]);
    }

    public function paginate(string $search = ''): LengthAwarePaginator
    {
        $query = FranchiseOwner::query();
        foreach (preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $query->where(function (Builder $query) use ($term) {
                foreach (['first_name', 'middle_name', 'last_name', 'suffix', 'address', 'contact_number'] as $field) {
                    $query->orWhere($field, 'like', '%'.$term.'%');
                }
            });
        }

        return $query->orderBy('last_name')->orderBy('first_name')->orderBy('id')
            ->paginate(10)->withQueryString();
    }

    public function assignFranchise(FranchiseOwner $owner, array $data): FranchiseOwnership
    {
        return DB::transaction(function () use ($owner, $data) {
            $franchise = Franchise::query()->lockForUpdate()->findOrFail($data['franchise_id']);
            $overlaps = $franchise->ownerships()->where(fn (Builder $query) => $query
                ->whereNull('ended_at')->orWhereDate('ended_at', '>', $data['started_at']))->exists();
            if ($overlaps) {
                throw ValidationException::withMessages(['franchise_id' => 'This franchise already has an overlapping ownership. Use an ownership transfer to change its owner.']);
            }

            if ($franchise->date_issued && $data['expiration_date'] < $franchise->date_issued->toDateString()) {
                throw ValidationException::withMessages(['expiration_date' => 'The expiration date must be on or after the franchise issue date.']);
            }
            $franchise->update(['expiration_date' => $data['expiration_date']]);

            return $owner->ownerships()->create(['franchise_id' => $franchise->id, 'started_at' => $data['started_at']]);
        });
    }

    public function create(array $data): FranchiseOwner
    {
        return FranchiseOwner::create($data);
    }

    public function update(FranchiseOwner $owner, array $data): FranchiseOwner
    {
        $owner->update($data);

        return $owner;
    }
}

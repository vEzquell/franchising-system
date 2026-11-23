<?php

namespace App\Services\Admin;

use App\Models\Franchise;
use App\Models\FranchiseOwner;
use App\Models\FranchiseOwnership;
use App\Models\FranchiseTransfer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    /** @return Collection<int, FranchiseOwner> */
    public function findNameMatches(string $firstName, string $lastName): Collection
    {
        if (max(mb_strlen($firstName), mb_strlen($lastName)) < 2) {
            return new Collection;
        }

        $query = FranchiseOwner::query();

        foreach (['first_name' => $firstName, 'last_name' => $lastName] as $field => $name) {
            if ($name !== '') {
                $prefix = strtr($name, ['!' => '!!', '%' => '!%', '_' => '!_']);
                $query->whereRaw("{$field} LIKE ? ESCAPE '!'", [$prefix.'%']);
            }
        }

        return $query->orderBy('last_name')->orderBy('first_name')->orderBy('id')->limit(5)
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'suffix', 'address']);
    }

    public function assignFranchise(FranchiseOwner $owner, array $data): FranchiseOwnership
    {
        return $this->saveFranchiseSetup($data, $owner);
    }

    private function createOwnership(FranchiseOwner $owner, Franchise $franchise, array $data): FranchiseOwnership
    {
        $startedAt = $franchise->date_issued?->toDateString() ?? $data['started_at'] ?? null;
        if ($startedAt === null || $startedAt > today()->toDateString()) {
            throw ValidationException::withMessages(['started_at' => 'The ownership start date must be today or earlier.']);
        }

        if ($data['expiration_date'] < $startedAt) {
            throw ValidationException::withMessages(['expiration_date' => 'The expiration date must be on or after the ownership start date.']);
        }

        $overlaps = $franchise->ownerships()->where(fn (Builder $query) => $query
            ->whereNull('ended_at')->orWhereDate('ended_at', '>', $startedAt))->exists();
        if ($overlaps) {
            throw ValidationException::withMessages(['franchise_id' => 'This franchise already has an overlapping ownership. Use an ownership transfer to change its owner.']);
        }

        $franchise->update([
            'date_issued' => $startedAt,
            'expiration_date' => $data['expiration_date'],
        ]);

        return $owner->ownerships()->create(['franchise_id' => $franchise->id, 'started_at' => $startedAt]);
    }

    public function create(array $data): FranchiseOwner
    {
        return FranchiseOwner::create($data);
    }

    public function createWithFranchise(array $data): FranchiseOwner
    {
        return $this->saveFranchiseSetup($data)->owner;
    }

    private function saveFranchiseSetup(array $data, ?FranchiseOwner $owner = null): FranchiseOwnership
    {
        $storedPaths = [];
        try {
            return DB::transaction(function () use ($data, $owner, &$storedPaths): FranchiseOwnership {
                $owner ??= $this->create(Arr::only($data, ['first_name', 'middle_name', 'last_name', 'suffix', 'address', 'contact_number']));
                $franchise = Franchise::query()->lockForUpdate()->findOrFail($data['franchise_id']);
                $ownership = $this->createOwnership($owner, $franchise, $data);

                if (isset($data['vehicle'])) {
                    app(VehicleService::class)->assign($franchise, [
                        'vehicle' => $data['vehicle'],
                        'started_at' => $ownership->started_at->toDateString(),
                    ]);
                }

                if (isset($data['liner_id'])) {
                    $franchise->liner()->associate($data['liner_id']);
                    $franchise->save();
                }

                if (isset($data['attachments'])) {
                    app(AttachmentService::class)->store($ownership->attachments(), $data['attachments'], 'ownership-attachments/'.$ownership->id, $storedPaths);
                }

                return $ownership->setRelation('owner', $owner);
            });
        } catch (\Throwable $exception) {
            if ($storedPaths) {
                Storage::disk('local')->delete($storedPaths);
            }
            throw $exception;
        }
    }

    public function update(FranchiseOwner $owner, array $data): FranchiseOwner
    {
        $owner->update($data);

        return $owner;
    }
}

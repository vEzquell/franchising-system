<?php

namespace App\Services\Admin;

use App\Models\Franchise;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FranchiseServices
{
    public function paginate(string $search = '', string $status = ''): LengthAwarePaginator
    {
        $currentOwnership = fn (Builder $query) => $query
            ->whereDate('started_at', '<=', today())
            ->where(fn (Builder $period) => $period->whereNull('ended_at')->orWhereDate('ended_at', '>', today()));

        return Franchise::query()
            ->when($search !== '', function (Builder $query) use ($search, $currentOwnership) {
                $query->where(function (Builder $query) use ($search, $currentOwnership) {
                    $query->where('franchise_number', 'like', '%'.$search.'%')
                        ->orWhereHas('ownerships', function (Builder $ownership) use ($search, $currentOwnership) {
                            $currentOwnership($ownership)->whereHas('owner', function (Builder $owner) use ($search) {
                                $owner->where('first_name', 'like', '%'.$search.'%')
                                    ->orWhere('last_name', 'like', '%'.$search.'%')
                                    ->orWhere('middle_name', 'like', '%'.$search.'%')
                                    ->orWhere('suffix', 'like', '%'.$search.'%');
                            });
                        });
                });
            })
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->with(['ownerships' => fn (HasMany $query) => $currentOwnership($query->getQuery())
                ->with('owner:id,first_name,middle_name,last_name,suffix')->orderByDesc('started_at')->orderByDesc('id')])
            ->with(['vehicleAssignments' => fn (HasMany $query) => $query->whereDate('started_at', '<=', today())
                ->where(fn (Builder $query) => $query->whereNull('ended_at')->orWhereDate('ended_at', '>', today()))->with('vehicle')])
            ->withCount('transactions')
            ->orderBy('franchise_number')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();
    }

    public function transactions(Franchise $franchise): LengthAwarePaginator
    {
        return $franchise->transactions()
            ->select(['id', 'franchise_id', 'transaction_type', 'status', 'created_at', 'approved_at'])
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(10)->withQueryString();
    }

    public function create(array $data): Franchise
    {
        return Franchise::create($data);
    }

    public function update(Franchise $franchise, array $data): Franchise
    {
        $franchise->update($data);

        return $franchise;
    }
}

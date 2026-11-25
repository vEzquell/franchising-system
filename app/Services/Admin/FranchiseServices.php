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
        return Franchise::query()
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('franchise_number', 'like', '%'.$search.'%')
                        ->orWhereHas('ownerships', function (Builder $ownership) use ($search) {
                            $ownership->current()->whereHas('owner', function (Builder $owner) use ($search) {
                                $owner->where('first_name', 'like', '%'.$search.'%')
                                    ->orWhere('last_name', 'like', '%'.$search.'%')
                                    ->orWhere('middle_name', 'like', '%'.$search.'%')
                                    ->orWhere('suffix', 'like', '%'.$search.'%');
                            });
                        });
                });
            })
            ->when($status !== '', fn (Builder $query) => $query->whereEffectiveStatus($status))
            ->with('liner:id,name,route')
            ->with(['ownerships' => fn (HasMany $query) => $query->current()
                ->with('owner:id,first_name,middle_name,last_name,suffix')->orderByDesc('started_at')->orderByDesc('id')])
            ->with(['vehicleAssignments' => fn (HasMany $query) => $query->current()->with('vehicle')])
            ->withCount('transactions')
            ->orderBy('franchise_number')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Franchise $franchise) => $franchise->append('recorded_status'));
    }

    public function transactions(Franchise $franchise): LengthAwarePaginator
    {
        return $franchise->transactions()
            ->with('type:code,name')
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

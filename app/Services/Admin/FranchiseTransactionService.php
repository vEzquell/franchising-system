<?php

namespace App\Services\Admin;

use App\Models\Franchise;
use App\Models\FranchiseTransaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FranchiseTransactionService
{
    public function updateStatus(FranchiseTransaction $transaction, string $status, User $processor): void
    {
        DB::transaction(function () use ($transaction, $status, $processor) {
            $franchise = Franchise::query()->lockForUpdate()->findOrFail($transaction->franchise_id);
            $transaction = FranchiseTransaction::query()->lockForUpdate()->findOrFail($transaction->id);

            if (! in_array($status, $transaction->nextStatuses(), true)) {
                throw ValidationException::withMessages(['status' => 'This transaction cannot move to the selected status. Refresh its details and try again.']);
            }

            if ($status === 'approved') {
                $this->applyCertificateDates($franchise, $transaction);
                if ($transfer = $transaction->amendment?->transfer) {
                    $ownership = app(OwnerService::class)->transfer($franchise, $transfer);
                    $transaction->attachments()->update(['franchise_ownership_id' => $ownership->id]);
                }
                if ($transaction->substitution) {
                    app(VehicleService::class)->substitute($franchise, $transaction->substitution);
                }
                $transaction->approved_at = now();
            }

            $transaction->status = $status;
            $transaction->processed_by = $processor->id;
            $transaction->save();
        });
    }

    private function applyCertificateDates(Franchise $franchise, FranchiseTransaction $transaction): void
    {
        $renewal = $transaction->renewal;
        $issueDate = $transaction->certificate_issue_date
            ?? $renewal?->renewal_date
            ?? $transaction->substitution?->substitution_date
            ?? today();
        $expiration = $renewal?->new_expiration_date ?? $franchise->expiration_date;

        if ($franchise->date_issued && $issueDate->lt($franchise->date_issued)) {
            throw ValidationException::withMessages(['status' => 'The certificate issue date precedes the current certificate.']);
        }

        if ($expiration && $expiration->lt($issueDate)) {
            throw ValidationException::withMessages(['status' => 'The certificate issue date is after its expiration.']);
        }

        if ($renewal && $franchise->expiration_date && $expiration->lte($franchise->expiration_date)) {
            throw ValidationException::withMessages(['status' => 'The requested renewal must extend the current expiration date.']);
        }

        $franchise->update(['date_issued' => $issueDate, 'expiration_date' => $expiration]);
    }

    public function paginate(string $search = '', string $status = ''): LengthAwarePaginator
    {
        return FranchiseTransaction::query()->with('franchise:id,franchise_number')->withCount('attachments')
            ->when($search !== '', fn (Builder $query) => $query->whereHas('franchise', fn (Builder $query) => $query->where('franchise_number', 'like', '%'.$search.'%')))
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(10)->withQueryString();
    }

    public function create(Franchise $franchise, array $data): FranchiseTransaction
    {
        $storedPaths = [];
        try {
            return DB::transaction(function () use ($franchise, $data, &$storedPaths) {
                $franchise = Franchise::query()->lockForUpdate()->findOrFail($franchise->id);
                $vehicle = null;
                if (! empty($data['vehicle'])) {
                    $vehicle = app(VehicleService::class)->resolve($data['vehicle']);
                    $transferReplacement = $data['transaction_type'] === 'amendment'
                        && $data['details']['amendment_type'] === 'transfer_ownership'
                        && ! empty($data['details']['change_vehicle']);
                    if ($data['transaction_type'] === 'substitution' || $transferReplacement) {
                        if ($vehicle->id === (int) ($data['details']['previous_vehicle_id'] ?? 0)) {
                            throw ValidationException::withMessages(['vehicle.id' => 'The replacement vehicle must differ from the previous vehicle.']);
                        }
                        $data['details']['new_vehicle_id'] = $vehicle->id;
                    }
                }
                $transaction = $franchise->transactions()->create([
                    'transaction_type' => $data['transaction_type'],
                    // Legacy required DATE column; created_at is the authoritative filing timestamp.
                    'date_filed' => today()->toDateString(),
                    'certificate_issue_date' => $data['certificate_issue_date'] ?? null,
                    'status' => 'pending',
                ]);
                $details = $data['details'];
                if ($data['transaction_type'] === 'renewal') {
                    $transaction->renewal()->create(Arr::only($details, ['franchise_owner_id', 'renewal_date', 'previous_expiration_date', 'new_expiration_date']));
                } elseif ($data['transaction_type'] === 'substitution') {
                    $transaction->substitution()->create(Arr::only($details, ['previous_vehicle_id', 'new_vehicle_id', 'substitution_date', 'reason']));
                } elseif ($data['transaction_type'] === 'amendment') {
                    $amendment = $transaction->amendment()->create(Arr::only($details, ['amendment_type', 'reason']));
                    if ($details['amendment_type'] === 'transfer_ownership') {
                        $amendment->transfer()->create(Arr::only($details, ['previous_owner_id', 'new_owner_id', 'transfer_date']));
                        if (! empty($details['change_vehicle'])) {
                            $transaction->substitution()->create([
                                ...Arr::only($details, ['previous_vehicle_id', 'new_vehicle_id', 'reason']),
                                'substitution_date' => $details['transfer_date'],
                            ]);
                        }
                    } else {
                        foreach ($details['changes'] as $change) {
                            $old = $change['field_name'] === 'route' ? null : $franchise->getRawOriginal($change['field_name']);
                            $amendment->changes()->create([
                                'field_name' => $change['field_name'], 'old_value' => $old, 'new_value' => $change['new_value'],
                            ]);
                        }
                    }
                }

                if ($vehicle) {
                    $snapshot = array_replace($vehicle->only(['make_type', 'plate_number', 'motor_number', 'chassis_number']), Arr::only($data['vehicle'], ['make_type', 'plate_number', 'motor_number', 'chassis_number']));
                    $transaction->vehicleDetails()->create([
                        'vehicle_id' => $vehicle->id,
                        'franchise_owner_id' => $data['transaction_type'] === 'application' ? ($details['franchise_owner_id'] ?? null) : null,
                        'vehicle_details' => $snapshot,
                    ]);
                }
                if (! empty($data['attachments'])) {
                    app(AttachmentService::class)->store(
                        $transaction->attachments(),
                        $data['attachments'],
                        'transaction-attachments/'.$transaction->id,
                        $storedPaths,
                        $transaction->attachmentOwnershipId(),
                    );
                }

                return $transaction;
            });
        } catch (\Throwable $exception) {
            if ($storedPaths) {
                Storage::disk('local')->delete($storedPaths);
            }
            throw $exception;
        }
    }
}

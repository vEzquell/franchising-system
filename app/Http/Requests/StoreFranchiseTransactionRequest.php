<?php

namespace App\Http\Requests;

use App\Models\FranchiseVehicleAssignment;
use App\Models\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFranchiseTransactionRequest extends FormRequest
{
    use ValidatesVehicle;

    public function authorize(): bool
    {
        return $this->user()->can($this->routeIs('transactions.*') ? 'view transactions' : 'view franchise');
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $totalSize = collect($this->file('attachments', []))->sum(fn ($item) => isset($item['file']) ? $item['file']->getSize() : 0);
            if ($totalSize > 6 * 1024 * 1024) {
                $validator->errors()->add('attachments', 'Attachments must total no more than 6 MB.');
            }
            if ($validator->errors()->isNotEmpty() || ! $this->has('vehicle')) {
                return;
            }
            $franchiseId = $this->route('franchise')?->id ?? $this->input('franchise_id');
            $assignments = FranchiseVehicleAssignment::where('franchise_id', $franchiseId)
                ->whereDate('started_at', '<=', today())
                ->where(fn ($query) => $query->whereNull('ended_at')->orWhereDate('ended_at', '>', today()));
            if ($this->input('transaction_type') === 'renewal') {
                if ($this->input('vehicle.mode') !== 'existing' || ! (clone $assignments)->where('vehicle_id', $this->input('vehicle.id'))->exists()) {
                    $validator->errors()->add('vehicle.id', 'Select the vehicle currently assigned to this franchise.');
                }
            }
            if ($this->input('transaction_type') === 'substitution' && ! (clone $assignments)->where('vehicle_id', $this->input('details.previous_vehicle_id'))->exists()) {
                $validator->errors()->add('details.previous_vehicle_id', 'The previous vehicle must be assigned to this franchise.');
            }
            if ($this->changesVehicleDuringTransfer()) {
                $date = $this->input('details.transfer_date');
                $previous = FranchiseVehicleAssignment::where('franchise_id', $franchiseId)
                    ->where('vehicle_id', $this->input('details.previous_vehicle_id'))
                    ->whereDate('started_at', '<=', $date)
                    ->where(fn ($query) => $query->whereNull('ended_at')->orWhereDate('ended_at', '>', $date));

                if (! $previous->exists()) {
                    $validator->errors()->add('details.previous_vehicle_id', 'The previous vehicle must be assigned to this franchise on the transfer date.');
                }
            }
            if ($this->input('transaction_type') === 'application' && ! $this->filled('details.franchise_owner_id')) {
                $validator->errors()->add('details.franchise_owner_id', 'Select the applicant / owner.');
            }
        });
    }

    private function changesVehicleDuringTransfer(): bool
    {
        return $this->input('transaction_type') === 'amendment'
            && $this->input('details.amendment_type') === 'transfer_ownership'
            && $this->boolean('details.change_vehicle');
    }

    public function rules(): array
    {
        $hasBuiltInWorkflow = TransactionType::hasBuiltInWorkflow($this->input('transaction_type'));
        $rules = [
            ...$this->vehicleRules(),
            'vehicle' => [
                Rule::requiredIf($this->changesVehicleDuringTransfer() || ($this->routeIs('transactions.store') && in_array($this->input('transaction_type'), ['application', 'renewal', 'substitution']))),
                Rule::prohibitedIf(! $hasBuiltInWorkflow || ($this->input('transaction_type') === 'amendment' && ! $this->changesVehicleDuringTransfer())),
                'array',
            ],
            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*.document_name' => ['required', 'string', 'max:255'],
            'attachments.*.document_type' => ['sometimes', 'required', 'string', 'max:100'],
            'attachments.*.file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'transaction_type' => ['bail', 'required', 'string', 'max:24', 'regex:/^[a-z0-9_]+$/', Rule::exists(TransactionType::class, 'code')->where('is_active', true)],
            ...($hasBuiltInWorkflow ? [
                'certificate_issue_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
                'details' => ['required', 'array'],
            ] : [
                'certificate_issue_date' => ['prohibited'],
                'details' => ['prohibited'],
                'remarks' => ['nullable', 'string', 'max:5000'],
            ]),
            ...($this->routeIs('transactions.store') ? ['franchise_id' => ['required', 'integer', 'exists:franchises,id']] : []),
        ];
        $owner = Rule::exists('franchise_ownerships', 'franchise_owner_id')->where(fn ($query) => $query
            ->where('franchise_id', ($this->route('franchise')?->id ?? $this->input('franchise_id')))->whereDate('started_at', '<=', today())
            ->where(fn ($period) => $period->whereNull('ended_at')->orWhereDate('ended_at', '>', today())));

        return [...$rules, ...match ($this->input('transaction_type')) {
            'renewal' => [
                'details.franchise_owner_id' => ['required', 'integer', $owner],
                'details.renewal_date' => ['required', 'date_format:Y-m-d'],
                'details.previous_expiration_date' => ['required', 'date_format:Y-m-d'],
                'details.new_expiration_date' => ['required', 'date_format:Y-m-d', 'after:details.previous_expiration_date'],
            ],
            'substitution' => [
                'details.previous_vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
                'details.new_vehicle_id' => [Rule::requiredIf($this->input('vehicle.mode') !== 'new'), 'nullable', 'integer', 'exists:vehicles,id', 'different:details.previous_vehicle_id'],
                'details.substitution_date' => ['required', 'date_format:Y-m-d'],
                'details.reason' => ['required', 'string', 'max:5000'],
            ],
            'amendment' => [
                'details.amendment_type' => ['required', Rule::in(['transfer_ownership', 'change_route', 'change_details'])],
                'details.reason' => ['required', 'string', 'max:5000'],
                ...($this->input('details.amendment_type') === 'transfer_ownership' ? [
                    'details.previous_owner_id' => ['required', 'integer', $owner],
                    'details.new_owner_id' => ['required', 'integer', 'exists:franchise_owners,id', 'different:details.previous_owner_id'],
                    'details.transfer_date' => ['required', 'date_format:Y-m-d'],
                    'details.change_vehicle' => ['sometimes', 'boolean'],
                    ...($this->changesVehicleDuringTransfer() ? [
                        'details.previous_vehicle_id' => ['required', 'integer', 'exists:vehicles,id', 'different:vehicle.id'],
                    ] : []),
                ] : [
                    'details.changes' => ['required', 'array', 'min:1', 'max:10'],
                    'details.changes.*.field_name' => ['required', 'distinct', Rule::in($this->input('details.amendment_type') === 'change_route' ? ['route'] : ['franchise_number', 'status', 'date_issued', 'expiration_date'])],
                    'details.changes.*.new_value' => ['required', 'string', 'max:5000'],
                ]),
            ],
            'application' => ['details.confirmed' => ['accepted'], 'details.franchise_owner_id' => ['nullable', 'integer', 'exists:franchise_owners,id']],
            default => [],
        }];
    }
}

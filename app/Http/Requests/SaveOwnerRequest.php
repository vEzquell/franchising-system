<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveOwnerRequest extends FormRequest
{
    use ValidatesOwnershipAttachments, ValidatesVehicle;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user->can('view owners') && (! $this->routeIs('owners.store', 'owners.franchises.store') || (
            (! $this->hasAny(['vehicle', 'liner_id']) || $user->can('view franchise'))
            && (! $this->has('attachments') || $user->can('view ownerships'))
        ));
    }

    protected function prepareForValidation(): void
    {
        foreach (['first_name', 'middle_name', 'last_name', 'suffix', 'address', 'contact_number'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $this->merge([$field => $value === '' ? null : $value]);
            }
        }
    }

    public function rules(): array
    {
        if ($this->routeIs('owners.franchises.store')) {
            return $this->franchiseRules(required: true);
        }

        return [
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'middle_name' => ['nullable', 'string', 'max:50'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            ...($this->routeIs('owners.store') ? $this->franchiseRules() : []),
        ];
    }

    private function franchiseRules(bool $required = false): array
    {
        $hasFranchise = $this->filled('franchise_id');
        $attachmentRules = $this->ownershipAttachmentRules(required: false);

        return [
            ...$this->vehicleRules(),
            ...$attachmentRules,
            'franchise_id' => [...($required ? [] : ['sometimes']), 'required', 'integer', 'exists:franchises,id'],
            'started_at' => [Rule::prohibitedIf(! $hasFranchise), 'nullable', 'date_format:Y-m-d'],
            'expiration_date' => [Rule::prohibitedIf(! $required && ! $hasFranchise), Rule::requiredIf($required || $hasFranchise), 'nullable', 'date_format:Y-m-d'],
            'liner_id' => [Rule::prohibitedIf(! $hasFranchise), 'sometimes', 'required', 'integer', 'exists:liners,id'],
            'vehicle' => [Rule::prohibitedIf(! $hasFranchise), 'sometimes', 'required', 'array'],
            'attachments' => [...$attachmentRules['attachments'], Rule::prohibitedIf(! $hasFranchise)],
        ];
    }
}

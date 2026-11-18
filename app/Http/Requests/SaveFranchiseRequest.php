<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveFranchiseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view franchise');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('franchise_number'))) {
            $this->merge(['franchise_number' => trim($this->input('franchise_number'))]);
        }
    }

    public function rules(): array
    {
        return [
            'franchise_number' => ['required', 'string', 'max:50', Rule::unique('franchises', 'franchise_number')->ignore($this->route('franchise'))],
            'status' => ['required', Rule::in(['active', 'expired', 'suspended', 'unavailable'])],
            'date_issued' => ['nullable', 'date_format:Y-m-d'],
            'expiration_date' => ['nullable', 'date_format:Y-m-d', ...($this->filled('date_issued') ? ['after_or_equal:date_issued'] : [])],
        ];
    }
}

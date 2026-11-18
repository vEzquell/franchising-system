<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view owners');
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
        return [
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'middle_name' => ['nullable', 'string', 'max:50'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:20'],
        ];
    }
}

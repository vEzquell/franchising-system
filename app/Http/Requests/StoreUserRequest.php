<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view users');
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'username'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash:ascii', Rule::unique('users', 'username')],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}

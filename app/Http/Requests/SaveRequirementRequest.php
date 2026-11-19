<?php

namespace App\Http\Requests;

use App\Models\Requirement;
use App\Models\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

class SaveRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('process transactions');
    }

    /**
     * @return array{transaction_type: list<string|Exists>, name: list<string|Unique>, description: list<string>}
     */
    public function rules(): array
    {
        $transactionType = $this->input('transaction_type');
        $requirement = $this->route('requirement');

        return [
            'transaction_type' => ['bail', 'required', 'string', 'max:24', 'regex:/^[a-z0-9_]+$/', Rule::exists(TransactionType::class, 'code')],
            'name' => ['bail', 'required', 'string', 'max:191', Rule::unique(Requirement::class)
                ->where('transaction_type', is_string($transactionType) ? $transactionType : '')
                ->ignore($requirement?->getKey())],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

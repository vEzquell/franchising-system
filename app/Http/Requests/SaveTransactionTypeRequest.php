<?php

namespace App\Http\Requests;

use App\Models\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class SaveTransactionTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('process transactions');
    }

    /**
     * @return array{name: list<string|Unique>, is_active: list<string>}
     */
    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:120', Rule::unique(TransactionType::class, 'name')
                ->ignore($this->route('transactionType')?->code, 'code')],
            'is_active' => ['required', 'boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\TransactionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FileFranchiseTransactionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('view transactions');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'franchise_id' => ['required', 'integer', 'exists:franchises,id'],
            'filings' => ['required', 'array', 'list', 'min:1'],
            'filings.*' => ['required', 'array:transaction_type,attachments'],
            'filings.*.transaction_type' => ['bail', 'required', 'string', 'distinct', 'max:24', 'regex:/^[a-z0-9_]+$/', Rule::exists(TransactionType::class, 'code')->where('is_active', true)],
            'filings.*.attachments' => ['sometimes', 'array', 'list'],
            'filings.*.attachments.*' => ['required', 'array:requirement_id,file'],
            'filings.*.attachments.*.requirement_id' => ['required', 'integer', 'distinct'],
            'filings.*.attachments.*.file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $totalSize = 0;
            foreach ($this->file('filings', []) as $filing) {
                foreach ($filing['attachments'] ?? [] as $attachment) {
                    $totalSize += $attachment['file']->getSize();
                }
            }

            if ($totalSize > 6 * 1024 * 1024) {
                $validator->errors()->add('filings', 'Attachments must total no more than 6 MB per submission.');
            }
        }];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOwnershipAttachmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view ownerships');
    }

    public function rules(): array
    {
        return [
            'attachments' => ['required', 'array', 'min:1', 'max:10'],
            'attachments.*.document_name' => ['required', 'string', 'max:255'],
            'attachments.*.document_type' => ['sometimes', 'required', 'string', 'max:100'],
            'attachments.*.file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $size = collect($this->file('attachments', []))->sum(fn ($item) => isset($item['file']) ? $item['file']->getSize() : 0);
            if ($size > 6 * 1024 * 1024) {
                $validator->errors()->add('attachments', 'Attachments must total no more than 6 MB.');
            }
        });
    }
}

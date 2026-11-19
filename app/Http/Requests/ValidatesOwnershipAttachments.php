<?php

namespace App\Http\Requests;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

trait ValidatesOwnershipAttachments
{
    protected function ownershipAttachmentRules(bool $required = true): array
    {
        return [
            'attachments' => [...($required ? ['required'] : ['sometimes']), 'array', 'min:1', 'max:10'],
            'attachments.*.document_name' => ['required', 'string', 'max:255'],
            'attachments.*.document_type' => ['sometimes', 'required', 'string', 'max:100'],
            'attachments.*.file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $files = $this->file('attachments', []);
            if (! is_array($files)) {
                return;
            }

            $size = collect($files)->sum(fn (mixed $item): int => is_array($item) && ($item['file'] ?? null) instanceof UploadedFile
                ? (int) $item['file']->getSize()
                : 0);
            if ($size > 6 * 1024 * 1024) {
                $validator->errors()->add('attachments', 'Attachments must total no more than 6 MB.');
            }
        });
    }
}

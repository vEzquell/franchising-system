<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOwnershipAttachmentsRequest extends FormRequest
{
    use ValidatesOwnershipAttachments;

    public function authorize(): bool
    {
        return $this->user()->can('view ownerships');
    }

    public function rules(): array
    {
        return $this->ownershipAttachmentRules();
    }
}

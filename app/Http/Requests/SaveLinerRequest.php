<?php

namespace App\Http\Requests;

use App\Models\Liner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class SaveLinerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view liners');
    }

    /**
     * @return array{name: list<string|Unique>, route: list<string|Unique>}
     */
    public function rules(): array
    {
        $liner = $this->route('liner');

        return [
            'name' => ['bail', 'required', 'string', 'max:191', Rule::unique(Liner::class, 'name')->ignore($liner?->getKey())],
            'route' => ['bail', 'required', 'string', 'max:191', Rule::unique(Liner::class, 'route')->ignore($liner?->getKey())],
        ];
    }
}

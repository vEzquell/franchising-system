<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignVehicleRequest extends FormRequest
{
    use ValidatesVehicle;

    public function authorize(): bool
    {
        return $this->user()->can('view franchise');
    }

    public function rules(): array
    {
        return [...$this->vehicleRules(), 'vehicle' => ['required', 'array'], 'started_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']];
    }
}

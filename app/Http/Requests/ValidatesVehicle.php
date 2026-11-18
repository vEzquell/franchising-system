<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

trait ValidatesVehicle
{
    public function vehicleRules(): array
    {
        $new = $this->input('vehicle.mode') === 'new';

        return [
            'vehicle' => ['sometimes', 'array'],
            'vehicle.mode' => ['required_with:vehicle', Rule::in(['existing', 'new'])],
            'vehicle.id' => [Rule::requiredIf($this->input('vehicle.mode') === 'existing'), 'nullable', 'integer', 'exists:vehicles,id'],
            'vehicle.make_type' => [Rule::requiredIf($new), 'nullable', 'string', 'max:50'],
            'vehicle.plate_number' => ['nullable', 'string', 'max:20', ...($new ? [Rule::unique('vehicles', 'plate_number')] : [])],
            'vehicle.motor_number' => ['nullable', 'string', 'max:100', ...($new ? [Rule::unique('vehicles', 'motor_number')] : [])],
            'vehicle.chassis_number' => ['nullable', 'string', 'max:100', ...($new ? [Rule::unique('vehicles', 'chassis_number')] : [])],
            ...($new ? ['vehicle.plate_number' => ['required_without_all:vehicle.motor_number,vehicle.chassis_number', 'nullable', 'string', 'max:20', Rule::unique('vehicles', 'plate_number')]] : []),
        ];
    }
}

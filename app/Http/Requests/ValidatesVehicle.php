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
            'vehicle.plate_number' => [
                ...($new ? ['required_without_all:vehicle.motor_number,vehicle.chassis_number'] : []),
                'nullable', 'string', 'max:20',
            ],
            'vehicle.motor_number' => ['nullable', 'string', 'max:100'],
            'vehicle.chassis_number' => ['nullable', 'string', 'max:100'],
        ];
    }
}

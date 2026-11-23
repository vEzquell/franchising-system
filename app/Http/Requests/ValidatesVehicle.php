<?php

namespace App\Http\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

trait ValidatesVehicle
{
    public function vehicleRules(): array
    {
        $new = $this->input('vehicle.mode') === 'new';
        $makeType = $this->input('vehicle.make_type');

        return [
            'vehicle' => ['sometimes', 'array'],
            'vehicle.mode' => ['required_with:vehicle', Rule::in(['existing', 'new'])],
            'vehicle.id' => [Rule::requiredIf($this->input('vehicle.mode') === 'existing'), 'nullable', 'integer', 'exists:vehicles,id'],
            'vehicle.make_type' => [Rule::requiredIf($new), 'nullable', 'string', 'max:50'],
            'vehicle.plate_number' => [
                ...($new ? ['required_without_all:vehicle.motor_number,vehicle.chassis_number'] : []),
                'nullable', 'string', 'max:20',
                ...($new && is_string($makeType) ? [
                    Rule::unique('vehicles', 'plate_number')->where(fn (Builder $query): Builder => $query->where('make_type', $makeType)),
                ] : []),
            ],
            'vehicle.motor_number' => ['nullable', 'string', 'max:100', ...($new ? [Rule::unique('vehicles', 'motor_number')] : [])],
            'vehicle.chassis_number' => ['nullable', 'string', 'max:100', ...($new ? [Rule::unique('vehicles', 'chassis_number')] : [])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['vehicle.plate_number.unique' => 'A vehicle with this make/type and plate number already exists.'];
    }
}

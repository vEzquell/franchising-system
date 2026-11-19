<script setup>
import { computed, watch } from 'vue'
import Field from './Field.vue'
const vehicle = defineModel({ type: Object, required: true })
const props = defineProps({ vehicles: { type: Array, default: () => [] }, errors: { type: Object, default: () => ({}) }, allowNew: { type: Boolean, default: true }, editable: { type: Boolean, default: true } })
const choices = computed(() => props.vehicles.map(item => ({ value: item.id, label: `${item.make_type}: ${item.plate_number || item.motor_number || item.chassis_number || '#' + item.id}` })))
watch(() => vehicle.value.id, id => {
    const selected = props.vehicles.find(item => item.id === Number(id))
    if (selected && vehicle.value.mode === 'existing') Object.assign(vehicle.value, { make_type: selected.make_type, plate_number: selected.plate_number ?? '', motor_number: selected.motor_number ?? '', chassis_number: selected.chassis_number ?? '' })
})
watch(() => vehicle.value.mode, () => Object.assign(vehicle.value, { id: '', make_type: '', plate_number: '', motor_number: '', chassis_number: '' }))
</script>
<template>
    <div class="space-y-4 rounded-lg border border-blue-100 p-4">
        <h3 class="font-semibold text-[#0D47A1]">Vehicle information</h3>
        <Field v-if="allowNew" v-model="vehicle.mode" name="vehicle.mode" label="Vehicle source" :options="[{ value: 'existing', label: 'Select registered vehicle' }, { value: 'new', label: 'Register a new vehicle' }]" :errors="errors" required />
        <Field v-if="vehicle.mode === 'existing'" v-model="vehicle.id" name="vehicle.id" label="Vehicle" :options="choices" :errors="errors" required />
        <p v-if="!allowNew && !vehicles.length" class="text-sm text-amber-700">Assign a vehicle to this franchise before requesting renewal.</p>
        <fieldset :disabled="!editable && vehicle.mode === 'existing'" class="space-y-3">
            <Field v-model="vehicle.make_type" name="vehicle.make_type" label="Make / type" :errors="errors" required />
            <Field v-model="vehicle.plate_number" name="vehicle.plate_number" label="Plate number" :errors="errors" />
            <Field v-model="vehicle.motor_number" name="vehicle.motor_number" label="Motor number" :errors="errors" />
            <Field v-model="vehicle.chassis_number" name="vehicle.chassis_number" label="Chassis number" :errors="errors" />
        </fieldset>
        <p v-if="vehicle.mode === 'new'" class="text-xs text-gray-500">Provide at least one plate, motor, or chassis number for a new vehicle.</p>
    </div>
</template>

<script setup>
import Field from './Field.vue'
const details = defineModel({ type: Object, required: true })
defineProps({ vehicles: Array, currentVehicles: Array, errors: Object })
</script>
<template>
    <div class="space-y-4">
        <Field v-model="details.previous_vehicle_id" name="details.previous_vehicle_id" label="Previous vehicle" :options="currentVehicles" :errors="errors" required />
        <Field v-model="details.substitution_date" name="details.substitution_date" label="Substitution date" type="date" :errors="errors" required />
        <Field v-model="details.reason" name="details.reason" label="Reason" type="textarea" :errors="errors" required />
    </div>
</template>

<script setup>
import Field from './Field.vue'
const details = defineModel({ type: Object, required: true })
defineProps({ errors: Object, owners: Array })
</script>
<template>
    <div class="space-y-4 text-sm">
        <Field v-model="details.franchise_owner_id" name="details.franchise_owner_id" label="Applicant / owner" :options="owners" :errors="errors" required />
        <label class="flex items-start gap-2"><input v-model="details.confirmed" type="checkbox" required class="mt-1" /> I confirm the application information is complete.</label>
        <p v-if="errors['details.confirmed']" role="alert" class="text-red-600">{{ errors['details.confirmed'] }}</p>
    </div>
</template>

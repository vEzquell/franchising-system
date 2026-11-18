<script setup>
import Field from './Field.vue'
const details = defineModel({ type: Object, required: true })
defineProps({ owners: Array, errors: Object })
</script>
<template>
    <div class="space-y-4">
        <Field v-model="details.franchise_owner_id" name="details.franchise_owner_id" label="Requesting owner" :options="owners" :errors="errors" required />
        <p v-if="!owners.length" class="text-sm text-amber-700">Assign a current owner to this franchise before requesting renewal.</p>
        <Field v-model="details.renewal_date" name="details.renewal_date" label="Renewal date" type="date" :errors="errors" required />
        <Field v-model="details.previous_expiration_date" name="details.previous_expiration_date" label="Previous expiration date" type="date" :errors="errors" required />
        <Field v-model="details.new_expiration_date" name="details.new_expiration_date" label="New expiration date" type="date" :min="details.previous_expiration_date" :errors="errors" required />
    </div>
</template>

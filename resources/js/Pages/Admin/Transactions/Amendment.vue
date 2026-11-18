<script setup>
import { watch } from 'vue'
import Field from './Field.vue'
import NewOwnerSelect from './NewOwnerSelect.vue'
const details = defineModel({ type: Object, required: true })
defineProps({ owners: Array, currentOwners: Array, errors: Object, canAddOwner: Boolean })
const emit = defineEmits(['add-owner'])
const fields = [
    { value: 'franchise_number', label: 'Franchise number' }, { value: 'status', label: 'Status' },
    { value: 'date_issued', label: 'Date issued' }, { value: 'expiration_date', label: 'Expiration date' },
]
watch(() => details.value.amendment_type, value => {
    details.value.changes = [{ field_name: value === 'change_route' ? 'route' : 'franchise_number', new_value: '' }]
})
</script>
<template>
    <div class="space-y-4">
        <Field v-model="details.amendment_type" name="details.amendment_type" label="Amendment type" :options="[{ value: 'transfer_ownership', label: 'Transfer ownership' }, { value: 'change_route', label: 'Change route' }, { value: 'change_details', label: 'Change details' }]" :errors="errors" required />
        <template v-if="details.amendment_type === 'transfer_ownership'">
            <Field v-model="details.previous_owner_id" name="details.previous_owner_id" label="Previous owner" :options="currentOwners" :errors="errors" required />
            <NewOwnerSelect v-model="details.new_owner_id" :owners="owners.filter(owner => owner.value !== details.previous_owner_id)" :error="errors['details.new_owner_id']" />
            <button v-if="canAddOwner" type="button" class="text-sm font-medium text-[#0D47A1] underline" @click="emit('add-owner')">Add new owner</button>
            <Field v-model="details.transfer_date" name="details.transfer_date" label="Transfer date" type="date" :errors="errors" required />
        </template>
        <template v-else>
            <div v-for="(change, index) in details.changes" :key="index" class="space-y-3 rounded-lg border border-blue-100 p-3">
                <Field v-model="change.field_name" :name="`details.changes.${index}.field_name`" label="Field to change" :options="details.amendment_type === 'change_route' ? [{ value: 'route', label: 'Route' }] : fields" :errors="errors" required />
                <Field v-model="change.new_value" :name="`details.changes.${index}.new_value`" label="Proposed value" :type="['date_issued', 'expiration_date'].includes(change.field_name) ? 'date' : 'text'" :errors="errors" required />
                <button v-if="details.changes.length > 1" type="button" class="text-sm text-red-700 underline" @click="details.changes.splice(index, 1)">Remove field</button>
            </div>
            <p v-if="errors['details.changes']" role="alert" class="text-sm text-red-600">{{ errors['details.changes'] }}</p>
            <button v-if="details.amendment_type === 'change_details' && details.changes.length < 4" type="button" class="text-sm text-[#0D47A1] underline" @click="details.changes.push({ field_name: '', new_value: '' })">Add another field</button>
        </template>
        <Field v-model="details.reason" name="details.reason" label="Reason" type="textarea" :errors="errors" required />
    </div>
</template>

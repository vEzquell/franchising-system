<script setup>
import { computed, inject, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { X } from 'lucide-vue-next'
import { formatOwnerName } from '@/lib/formatOwnerName'
import http from '@/lib/http'
import Field from './Field.vue'
import VehicleForm from './VehicleForm.vue'
import Attachments from './Attachments.vue'
import FranchiseSelect from './FranchiseSelect.vue'
import Renewal from './Renewal.vue'
import Amendment from './Amendment.vue'
import Substitution from './Substitution.vue'
import Application from './Application.vue'
import OwnerForm from '../Owner/Create.vue'

const open = defineModel('open', { type: Boolean, default: false })
const props = defineProps({ initialFranchise: { type: Object, default: null }, transactionTypes: { type: Array, required: true } })
const franchise = ref(null)
const emit = defineEmits(['created'])
const route = inject('route')
const dialog = ref(null)
const step = ref(1)
const saving = ref(false)
const loading = ref(false)
const optionsError = ref('')
const errorMessage = ref('')
const errors = ref({})
const ownerFormOpen = ref(false)
const page = usePage()
const canAddOwner = computed(() => page.props.auth?.isSuperAdmin || page.props.auth?.permissions?.includes('view owners'))
const options = ref({ owners: [], current_owner_ids: [], vehicles: [] })
const form = reactive({ transaction_type: '', certificate_issue_date: '', remarks: '' })
const details = ref({})
const vehicle = ref({ mode: 'existing', id: '', make_type: '', plate_number: '', motor_number: '', chassis_number: '' })
const attachments = ref([])
const selectedType = computed(() => props.transactionTypes.find(type => type.value === form.transaction_type))
const workflow = computed(() => selectedType.value?.workflow)
const isTransfer = computed(() => workflow.value === 'amendment' && details.value.amendment_type === 'transfer_ownership')
const requiresVehicle = computed(() => ['application', 'renewal', 'substitution'].includes(workflow.value))
const hasVehicleStep = computed(() => requiresVehicle.value || isTransfer.value)
const includesVehicle = computed(() => requiresVehicle.value || (isTransfer.value && details.value.change_vehicle === '1'))
const attachmentsStep = computed(() => hasVehicleStep.value ? 4 : 3)
const title = computed(() => {
    if (step.value === 1) return 'Add Transaction'
    if (step.value === attachmentsStep.value) return 'Attachments'
    if (step.value === 3) return 'Vehicle information'
    return `${selectedType.value?.label ?? 'Transaction'} details`
})
const owners = computed(() => options.value.owners.map(owner => ({ value: owner.id, label: formatOwnerName(owner) })))
const currentOwners = computed(() => owners.value.filter(owner => options.value.current_owner_ids.includes(owner.value)))
const currentVehicles = computed(() => (options.value.current_vehicles ?? []).map(item => ({ value: item.id, label: `${item.make_type}: ${item.plate_number || item.motor_number || item.chassis_number || '#' + item.id}` })))
const transactionRequirements = computed(() => (options.value.requirements ?? []).filter(requirement => requirement.transaction_type === form.transaction_type))
let optionsRequest
function localDate() {
    const date = new Date()
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}
function resetDetails() {
    vehicle.value = { mode: 'existing', id: '', make_type: '', plate_number: '', motor_number: '', chassis_number: '' }
    details.value = {
        franchise_owner_id: '', renewal_date: localDate(),
        previous_expiration_date: franchise.value?.expiration_date?.slice(0, 10) ?? '', new_expiration_date: '',
        previous_vehicle_id: '', new_vehicle_id: '', substitution_date: localDate(), reason: '',
        amendment_type: 'change_details', previous_owner_id: '', new_owner_id: '', transfer_date: localDate(), change_vehicle: '0',
        changes: [{ field_name: 'franchise_number', new_value: '' }], confirmed: false,
    }
}
watch(() => form.transaction_type, resetDetails)
async function loadOptions() {
    optionsRequest?.abort()
    const request = new AbortController()
    optionsRequest = request
    loading.value = true
    optionsError.value = ''
    try {
        const { data } = await http.get(route('transactions.options', franchise.value.id), { signal: request.signal })
        if (!request.signal.aborted) {
            options.value = data
            if (data.current_vehicles?.length === 1) {
                const current = data.current_vehicles[0]
                details.value.previous_vehicle_id = current.id
                if (workflow.value === 'renewal') Object.assign(vehicle.value, { ...current, mode: 'existing' })
            }
        }
    } catch (error) {
        if (!request.signal.aborted) optionsError.value = 'Unable to load owners and vehicles.'
    } finally {
        if (optionsRequest === request) loading.value = false
    }
}
watch(open, async value => {
    await nextTick()
    if (value) {
        franchise.value = props.initialFranchise
        attachments.value = []
        step.value = 1
        errors.value = {}
        errorMessage.value = ''
        options.value = { owners: [], current_owner_ids: [], vehicles: [] }
        const defaultType = props.transactionTypes.find(type => type.workflow === 'renewal') ?? props.transactionTypes[0]
        Object.assign(form, { transaction_type: defaultType?.value ?? '', certificate_issue_date: localDate(), remarks: '' })
        resetDetails()
        if (!dialog.value.open) dialog.value.showModal()
        if (franchise.value) loadOptions()
    } else {
        optionsRequest?.abort()
        dialog.value.close()
    }
})
function close() {
    if (!saving.value) open.value = false
}
function ownerSaved(_message, owner) {
    options.value.owners.push(owner)
    details.value.new_owner_id = owner.id
    delete errors.value['details.new_owner_id']
}
async function submit() {
    if (!selectedType.value) return
    if (step.value < attachmentsStep.value) {
        step.value++
        await nextTick()
        dialog.value.querySelector('input:not([type="hidden"]), select, textarea, button[type="submit"]')?.focus()
        return
    }
    if (saving.value) return
    if (!franchise.value) { errors.value = { franchise_id: 'Select a franchise from the search results.' }; return }
    if (attachments.value.some(item => item.file.size > 5242880) || attachments.value.reduce((total, item) => total + item.file.size, 0) > 6291456) { errors.value = { attachments: 'Use files up to 5 MB each and 6 MB total.' }; return }
    saving.value = true
    errors.value = {}
    errorMessage.value = ''
    try {
        if (workflow.value === 'substitution') details.value.new_vehicle_id = vehicle.value.mode === 'existing' ? vehicle.value.id : null
        const payload = new FormData()
        const append = (key, value) => {
            if (value === null || value === undefined || value === '') return
            if (value instanceof File) payload.append(key, value)
            else if (typeof value === 'object') Object.entries(value).forEach(([child, item]) => append(`${key}[${child}]`, item))
            else payload.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
        }
        const data = {
            transaction_type: form.transaction_type,
            franchise_id: franchise.value.id,
            attachments: attachments.value,
            ...(workflow.value === 'general' ? { remarks: form.remarks } : { certificate_issue_date: form.certificate_issue_date, details: details.value }),
        }
        if (includesVehicle.value) data.vehicle = vehicle.value
        Object.entries(data).forEach(([key, value]) => append(key, value))
        await http.post(route('transactions.store'), payload)
        open.value = false
        emit('created')
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = Object.fromEntries(Object.entries(error.response.data.errors ?? {}).map(([field, messages]) => [field, messages[0]]))
            if (errors.value.transaction_type) step.value = 1
            else if (Object.keys(errors.value).some(field => !field.startsWith('attachments') && !field.startsWith('vehicle') && !(isTransfer.value && ['details.previous_vehicle_id', 'details.change_vehicle'].includes(field)))) step.value = 2
            else if (hasVehicleStep.value && Object.keys(errors.value).some(field => !field.startsWith('attachments'))) step.value = 3
            errorMessage.value = 'Please check the highlighted fields.'
        } else errorMessage.value = 'Unable to save the transaction. Refresh the page and try again.'
    } finally {
        saving.value = false
    }
}
watch(franchise, value => {
    optionsRequest?.abort()
    options.value = { owners: [], current_owner_ids: [], vehicles: [] }
    optionsError.value = ''
    loading.value = false
    resetDetails()
    if (value && open.value) loadOptions()
})
onBeforeUnmount(() => optionsRequest?.abort())
</script>
<template>
    <Teleport to="body">
        <dialog ref="dialog" aria-labelledby="transaction-flow-title" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl backdrop:bg-black/50" @cancel.prevent="close" @close="open = false">
            <form class="space-y-4" @submit.prevent="submit">
                <div class="flex items-center justify-between gap-4">
                    <div><p class="text-xs text-gray-500">Step {{ step }} of {{ attachmentsStep }}</p><h2 id="transaction-flow-title" class="text-lg font-bold text-[#0D47A1]">{{ title }}</h2></div>
                    <button type="button" :disabled="saving" aria-label="Close transaction" class="rounded-lg p-2 hover:bg-blue-50 disabled:opacity-50" @click="close"><X class="h-5 w-5" /></button>
                </div>

                <p v-if="errorMessage" role="alert" class="text-sm text-red-600">{{ errorMessage }}</p>
                <p v-if="loading" role="status" class="text-sm text-gray-500">Loading owners and vehicles...</p>
                <p v-if="optionsError" role="alert" class="text-sm text-red-600">{{ optionsError }} <button type="button" class="underline" @click="loadOptions">Retry</button></p>
                <fieldset :disabled="saving" class="space-y-4">
                    <template v-if="step === 1">
                        <Field v-model="form.transaction_type" name="transaction_type" label="Transaction type" :options="transactionTypes" :errors="errors" required />
                        <p v-if="!transactionTypes.length" class="text-sm text-gray-600">There are no active transaction types available for filing.</p>
                    </template>
                    <template v-else-if="step === 2">
                        <FranchiseSelect v-model="franchise" :error="errors.franchise_id" />
                        <p v-if="!franchise" class="text-sm text-gray-500">Search and select a franchise to fill in the details.</p>
                        <div v-if="franchise" class="space-y-4">
                            <Field v-if="workflow === 'general'" v-model="form.remarks" name="remarks" label="Remarks (optional)" type="textarea" :errors="errors" />
                            <template v-else>
                                <Field v-model="form.certificate_issue_date" name="certificate_issue_date" label="Requested certificate issue date" type="date" :errors="errors" required />
                                <Renewal v-if="workflow === 'renewal'" v-model="details" :owners="currentOwners" :errors="errors" />
                                <Amendment v-else-if="workflow === 'amendment'" v-model="details" :owners="owners" :current-owners="currentOwners" :errors="errors" :can-add-owner="canAddOwner && !loading && !optionsError" @add-owner="ownerFormOpen = true" />
                                <Substitution v-else-if="workflow === 'substitution'" v-model="details" :current-vehicles="currentVehicles" :errors="errors" />
                                <Application v-else v-model="details" :owners="owners" :errors="errors" />
                            </template>
                        </div>
                    </template>
                    <template v-else-if="step < attachmentsStep">
                        <template v-if="isTransfer">
                            <Field v-model="details.change_vehicle" name="details.change_vehicle" label="Will the vehicle change?" :options="[{ value: '0', label: 'No, keep the current vehicle' }, { value: '1', label: 'Yes, replace the vehicle' }]" :errors="errors" required />
                            <Field v-if="includesVehicle" v-model="details.previous_vehicle_id" name="details.previous_vehicle_id" label="Vehicle to replace" :options="currentVehicles" :errors="errors" required />
                            <p v-if="includesVehicle && !currentVehicles.length" class="text-sm text-amber-700">This franchise has no current vehicle to replace.</p>
                            <div v-if="!includesVehicle" class="rounded-lg bg-blue-50 p-3 text-sm">
                                <p class="font-medium">Current vehicle</p>
                                <p v-for="item in currentVehicles" :key="item.value">{{ item.label }}</p>
                                <p v-if="!currentVehicles.length">No vehicle assigned.</p>
                            </div>
                        </template>
                        <VehicleForm v-if="includesVehicle" v-model="vehicle" :vehicles="workflow === 'renewal' ? (options.current_vehicles ?? []) : options.vehicles.filter(item => item.id !== details.previous_vehicle_id)" :allow-new="workflow !== 'renewal'" :errors="errors" />
                    </template>
                    <template v-else>
                        <section v-if="transactionRequirements.length" aria-labelledby="transaction-requirements-title" class="space-y-2 rounded-lg border border-blue-100 bg-blue-50 p-4 text-sm text-slate-700">
                            <h3 id="transaction-requirements-title" class="font-semibold text-[#0D47A1]">Transaction requirements</h3>
                            <ul class="list-disc space-y-2 pl-5">
                                <li v-for="requirement in transactionRequirements" :key="requirement.id" class="break-words">
                                    <p class="font-medium">{{ requirement.name }}</p>
                                    <p v-if="requirement.description" class="whitespace-pre-line text-slate-600">{{ requirement.description }}</p>
                                </li>
                            </ul>
                        </section>
                        <Attachments v-model="attachments" :errors="errors" />
                    </template>
                </fieldset>
                <p v-if="step === attachmentsStep" class="text-xs text-gray-500">Submitting saves the complete request as pending.</p>
                <div class="flex justify-end gap-3 border-t border-blue-50 pt-4">
                    <button type="button" :disabled="saving" class="rounded-lg border px-4 py-2 text-sm disabled:opacity-50" @click="close">Cancel</button>
                    <button v-if="step > 1" type="button" :disabled="saving" class="rounded-lg border px-4 py-2 text-sm disabled:opacity-50" @click="step--">Back</button>
                    <button type="submit" :disabled="!selectedType || saving || (step > 1 && (loading || !!optionsError || !franchise))" class="rounded-lg bg-[#2196F3] px-4 py-2 text-sm font-semibold text-white hover:bg-[#0D47A1] disabled:opacity-50">{{ saving ? 'Saving...' : step < attachmentsStep ? 'Next' : 'Submit transaction' }}</button>
                </div>
            </form>
        </dialog>
    </Teleport>
    <OwnerForm v-model:open="ownerFormOpen" @saved="ownerSaved" />
</template>

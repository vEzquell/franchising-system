<script setup>
import { computed, inject, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Users, X } from 'lucide-vue-next'
import http from '@/lib/http'
import { formatOwnerName } from '@/lib/formatOwnerName'
import Attachments from '../Transactions/Attachments.vue'
import FranchiseSelect from '../Transactions/FranchiseSelect.vue'
import VehicleForm from '../Transactions/VehicleForm.vue'

const open = defineModel('open', { type: Boolean, default: false })
const props = defineProps({ owner: { type: Object, default: null }, withFranchise: Boolean, assignOnly: Boolean })
const emit = defineEmits(['saved'])
const route = inject('route')
const dialog = ref(null)
const processing = ref(false)
const errors = ref({})
const errorMessage = ref('')
const fields = [
    { key: 'first_name', label: 'First name', max: 50, required: true },
    { key: 'middle_name', label: 'Middle Initial', max: 50 },
    { key: 'last_name', label: 'Last name', max: 50, required: true },
    { key: 'suffix', label: 'Suffix (e.g. Jr., Sr., III)', max: 20 },
    { key: 'address', label: 'Address', max: 255 },
    { key: 'contact_number', label: 'Contact number', max: 20, type: 'tel' },
]
const form = reactive({ first_name: '', middle_name: '', last_name: '', suffix: '', address: '', contact_number: '' })
const title = computed(() => props.assignOnly ? 'Assign Franchise' : props.owner ? 'Edit Owner' : 'Add Owner')
const page = usePage()
const can = permission => page.props.auth?.isSuperAdmin || page.props.auth?.permissions?.includes(permission)
const canLinkVehicleAndRoute = computed(() => can('view franchise'))
const canAddAttachments = computed(() => can('view ownerships'))
const wizard = computed(() => props.assignOnly || (props.withFranchise && !props.owner))
const steps = computed(() => [
    ...(props.assignOnly ? [] : [{ id: 'owner', label: 'Owner' }]),
    ...(wizard.value ? [{ id: 'franchise', label: 'Franchise' }] : []),
    ...(wizard.value && canLinkVehicleAndRoute.value ? [{ id: 'vehicle', label: 'Vehicle & route' }] : []),
    ...(wizard.value && canAddAttachments.value ? [{ id: 'attachments', label: 'Attachments' }] : []),
])
const step = ref(0)
const currentStep = computed(() => steps.value[step.value]?.id)
const nameMatches = ref([])
const nameMatchError = ref(false)
const isLastStep = computed(() => step.value === steps.value.length - 1)
const franchise = ref(null)
const issueDate = computed(() => franchise.value?.date_issued?.slice(0, 10) ?? '')
const startedAt = ref('')
const expirationDate = ref('')
const currentVehicle = ref(null)
const vehicles = ref([])
const liners = ref([])
const linerId = ref('')
const currentLinerId = ref('')
const linkVehicle = ref(false)
const vehicle = ref({})
const files = ref([])
const loadingOptions = ref(false)
const optionsError = ref(false)
let optionsRequest

watch(() => open.value && !props.owner && currentStep.value === 'owner'
    ? { first_name: form.first_name.trim(), last_name: form.last_name.trim() }
    : null, (names, _, onCleanup) => {
    nameMatches.value = []
    nameMatchError.value = false
    if (!names || Math.max([...names.first_name].length, [...names.last_name].length) < 2) return

    const request = new AbortController()
    const debounce = setTimeout(async () => {
        try {
            const { data } = await http.get(route('owners.name-matches'), { params: names, signal: request.signal })
            if (!request.signal.aborted) nameMatches.value = data.owners
        } catch {
            if (!request.signal.aborted) nameMatchError.value = true
        }
    }, 300)

    onCleanup(() => {
        clearTimeout(debounce)
        request.abort()
    })
})

async function loadOptions() {
    optionsRequest?.abort()
    const request = new AbortController()
    optionsRequest = request
    loadingOptions.value = true
    optionsError.value = false
    try {
        const { data } = await http.get(route('owners.franchises.setup-options', franchise.value.id), { signal: request.signal })
        if (request.signal.aborted) return
        currentVehicle.value = data.current_vehicle
        vehicles.value = data.vehicles
        liners.value = data.liners
        currentLinerId.value = data.liner_id ?? ''
        linerId.value = currentLinerId.value
        linkVehicle.value = !data.current_vehicle
    } catch {
        if (!request.signal.aborted) optionsError.value = true
    } finally {
        if (optionsRequest === request) loadingOptions.value = false
    }
}

watch(franchise, selected => {
    optionsRequest?.abort()
    startedAt.value = issueDate.value
    expirationDate.value = selected?.expiration_date?.slice(0, 10) ?? ''
    currentVehicle.value = null
    vehicle.value = { mode: 'new', id: '', make_type: '', plate_number: '', motor_number: '', chassis_number: '' }
    linkVehicle.value = false
    vehicles.value = []
    liners.value = []
    linerId.value = ''
    currentLinerId.value = ''
    loadingOptions.value = false
    optionsError.value = false
    files.value = []
    if (selected && canLinkVehicleAndRoute.value) loadOptions()
})

watch(open, async value => {
    await nextTick()
    if (value) {
        for (const field of fields) form[field.key] = props.owner?.[field.key] ?? ''
        franchise.value = null
        step.value = 0
        errors.value = {}
        errorMessage.value = ''
        if (!dialog.value.open) dialog.value.showModal()
        focusStep()
    } else {
        optionsRequest?.abort()
        dialog.value.close()
    }
})
function close() {
    if (!processing.value) open.value = false
}
async function goToStep(index) {
    step.value = index
    await focusStep()
}
async function focusStep() {
    await nextTick()
    dialog.value?.querySelector('[data-owner-step] input:not(:disabled), [data-owner-step] select:not(:disabled), [data-owner-step] button:not(:disabled)')?.focus()
}

function submit() {
    if (currentStep.value === 'franchise' && !franchise.value) {
        errors.value = { franchise_id: 'Select a franchise from the search results.' }
        return
    }
    if (!isLastStep.value) {
        goToStep(step.value + 1)
        return
    }
    save(wizard.value)
}

function saveOwnerOnly() {
    if (dialog.value.querySelector('form').reportValidity()) save(false)
}

async function save(withAssignment) {
    if (processing.value) return
    processing.value = true
    errors.value = {}
    errorMessage.value = ''
    try {
        let payload = { ...form }
        if (withAssignment) {
            payload = new FormData()
            const addValue = (key, value) => {
                if (value !== '' && value !== null && value !== undefined) payload.append(key, value)
            }
            if (!props.assignOnly) Object.entries(form).forEach(([key, value]) => addValue(key, value))
            payload.append('franchise_id', franchise.value.id)
            addValue('started_at', startedAt.value)
            payload.append('expiration_date', expirationDate.value)
            if (canLinkVehicleAndRoute.value) {
                if (linerId.value !== currentLinerId.value) addValue('liner_id', linerId.value)
                if (linkVehicle.value && !currentVehicle.value) {
                    Object.entries(vehicle.value).forEach(([key, value]) => addValue(`vehicle[${key}]`, value))
                }
            }
            if (canAddAttachments.value) files.value.forEach((item, index) => {
                payload.append(`attachments[${index}][file]`, item.file)
                payload.append(`attachments[${index}][document_name]`, item.document_name)
            })
        }
        const url = props.assignOnly ? route('owners.franchises.store', props.owner.id)
            : props.owner ? route('owners.update', props.owner.id) : route('owners.store')
        const { data } = await http.post(url, payload)
        open.value = false
        emit('saved', data.message, data.owner)
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = Object.fromEntries(Object.entries(error.response.data.errors ?? {}).map(([field, messages]) => [field, messages[0]]))
            if (wizard.value) {
                const fieldsWithErrors = Object.keys(errors.value)
                const errorStep = fieldsWithErrors.some(field => fields.some(item => item.key === field)) ? 'owner'
                    : fieldsWithErrors.some(field => ['franchise_id', 'started_at', 'expiration_date'].includes(field)) ? 'franchise'
                        : fieldsWithErrors.some(field => field === 'liner_id' || field.startsWith('vehicle')) ? 'vehicle' : 'attachments'
                const index = steps.value.findIndex(item => item.id === errorStep)
                if (index >= 0) goToStep(index)
                else errorMessage.value = 'Check the information you entered and try again.'
            }
        } else errorMessage.value = error.response?.status === 403 ? 'You do not have permission to manage owners.'
            : [401, 419].includes(error.response?.status) ? 'Your session ended. Refresh the page and sign in again.'
            : props.assignOnly ? 'Unable to assign the franchise. Please try again.' : 'Unable to save the owner. Please try again.'
    } finally {
        processing.value = false
    }
}
onBeforeUnmount(() => optionsRequest?.abort())
</script>
<template>
    <Teleport to="body">
        <dialog ref="dialog" aria-labelledby="owner-form-title" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] overflow-y-auto rounded-xl border border-[#90CAF9]/50 bg-white p-0 shadow-2xl backdrop:bg-black/50" :class="wizard ? 'max-w-xl' : 'max-w-md'" @cancel.prevent="close" @close="open = false">
            <div class="flex items-center justify-between gap-4 border-b border-[#E3F2FD] p-5">
                <div class="flex items-center gap-2"><Users class="h-6 w-6 text-[#0D47A1]" /><h2 id="owner-form-title" class="text-xl font-bold text-[#0D47A1]">{{ title }}</h2></div>
                <button type="button" aria-label="Close owner form" :disabled="processing" class="rounded-lg p-2 text-gray-500 hover:bg-blue-50 disabled:opacity-50" @click="close"><X class="h-5 w-5" /></button>
            </div>
            <ol v-if="wizard" aria-label="Owner setup steps" class="flex flex-wrap gap-2 border-b border-blue-100 bg-blue-50/60 px-5 py-3 text-xs font-semibold">
                <li v-for="(item, index) in steps" :key="item.id" :aria-current="step === index ? 'step' : undefined" class="rounded-lg px-2.5 py-1.5" :class="step === index ? 'bg-[#0D47A1] text-white' : 'text-slate-600'">{{ index + 1 }}. {{ item.label }}</li>
            </ol>
            <form class="space-y-4 p-5" @submit.prevent="submit">
                <p v-if="assignOnly" class="text-sm text-gray-600">Owner: <strong>{{ formatOwnerName(owner) }}</strong></p>
                <p v-if="errorMessage" role="alert" class="text-sm text-red-600">{{ errorMessage }}</p>
                <fieldset data-owner-step :disabled="processing" class="space-y-4">
                    <template v-if="currentStep === 'owner'">
                        <div v-for="field in fields" :key="field.key">
                            <label :for="`owner-${field.key}`" class="mb-2 block text-sm font-medium text-gray-700">{{ field.label }} <span v-if="field.required" aria-hidden="true" class="text-red-600">*</span></label>
                            <input v-uppercase :id="`owner-${field.key}`" v-model="form[field.key]" :type="field.type ?? 'text'" :required="field.required" :maxlength="field.max" :aria-invalid="!!errors[field.key]" :aria-describedby="`owner-${field.key}-error`" class="w-full rounded-lg border border-[#90CAF9] px-3 py-2.5 text-sm focus:outline-[#2196F3]" />
                            <p :id="`owner-${field.key}-error`" role="alert" class="mt-1 text-sm text-red-600">{{ errors[field.key] }}</p>
                            <div v-if="field.key === 'last_name' && !owner" aria-live="polite">
                                <div v-if="nameMatches.length" class="mt-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">
                                    <p class="font-semibold">Possible existing owners</p>
                                    <ul class="mt-1 list-disc space-y-1 pl-5">
                                        <li v-for="match in nameMatches" :key="match.id">
                                            {{ formatOwnerName(match) }}<span v-if="match.address" class="block text-xs">{{ match.address }}</span>
                                        </li>
                                    </ul>
                                </div>
                                <p v-else-if="nameMatchError" class="mt-2 text-xs text-gray-600">Unable to check for existing owners right now.</p>
                            </div>
                        </div>
                    </template>
                    <template v-else-if="currentStep === 'franchise'">
                        <FranchiseSelect v-if="open" v-model="franchise" search-route="owners.franchises" :error="errors.franchise_id" />
                        <div v-if="franchise" class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="setup-owner-start" class="mb-2 block text-sm font-medium">{{ issueDate ? 'Ownership start date' : 'Franchise issue date' }}</label>
                                <input id="setup-owner-start" v-model="startedAt" type="date" required :readonly="!!issueDate" :aria-invalid="!!errors.started_at" class="w-full rounded-lg border border-blue-300 px-3 py-2.5 text-sm read-only:bg-slate-50" />
                                <p class="mt-1 text-xs text-gray-600">{{ issueDate ? 'Uses the franchise issue date.' : 'Also saved as the ownership start date.' }}</p>
                                <p v-if="errors.started_at" role="alert" class="mt-1 text-sm text-red-600">{{ errors.started_at }}</p>
                            </div>
                            <div>
                                <label for="setup-franchise-expiration" class="mb-2 block text-sm font-medium">Franchise expiration date</label>
                                <input id="setup-franchise-expiration" v-model="expirationDate" type="date" required :min="startedAt || undefined" :aria-invalid="!!errors.expiration_date" class="w-full rounded-lg border border-blue-300 px-3 py-2.5 text-sm" />
                                <p v-if="errors.expiration_date" role="alert" class="mt-1 text-sm text-red-600">{{ errors.expiration_date }}</p>
                            </div>
                        </div>
                        <p v-if="assignOnly" class="text-xs text-gray-500">Assign an unassigned franchise here. Use an ownership transfer for franchises with an existing owner.</p>
                    </template>
                    <template v-else-if="currentStep === 'vehicle'">
                        <p v-if="loadingOptions" role="status" class="text-sm text-gray-600">Loading vehicle and route options...</p>
                        <p v-else-if="optionsError" role="alert" class="text-sm text-red-600">Unable to load vehicles and routes. <button type="button" class="underline" @click="loadOptions">Retry</button></p>
                        <template v-else>
                            <div v-if="currentVehicle" class="rounded-lg border border-blue-100 bg-blue-50 p-3 text-sm text-slate-700">
                                <p class="font-semibold text-[#0D47A1]">Current vehicle</p>
                                <p>{{ [currentVehicle.make_type, currentVehicle.plate_number, currentVehicle.motor_number, currentVehicle.chassis_number].filter(Boolean).join(' · ') }}</p>
                                <p class="mt-1 text-xs">This vehicle stays linked to the franchise.</p>
                            </div>
                            <template v-else>
                                <label class="flex items-center gap-2 text-sm font-medium"><input v-model="linkVehicle" type="checkbox" class="size-4 accent-[#0D47A1]" /> Link a vehicle</label>
                                <VehicleForm v-if="linkVehicle" v-model="vehicle" :vehicles="vehicles" :errors="errors" :editable="false" />
                            </template>
                            <p v-if="errors.vehicle || ((vehicle.mode === 'new' || currentVehicle) && errors['vehicle.id'])" role="alert" class="text-sm text-red-600">{{ errors.vehicle || errors['vehicle.id'] }}</p>
                            <div>
                                <label for="setup-franchise-route" class="mb-2 block text-sm font-medium">Route / liner</label>
                                <select id="setup-franchise-route" v-model="linerId" :aria-invalid="!!errors.liner_id" class="w-full rounded-lg border border-blue-300 bg-white px-3 py-2.5 text-sm">
                                    <option value="">{{ currentLinerId ? 'Keep current route' : 'No route selected' }}</option>
                                    <option v-for="liner in liners" :key="liner.id" :value="liner.id">{{ liner.route }} ({{ liner.name }})</option>
                                </select>
                                <p v-if="errors.liner_id" role="alert" class="mt-1 text-sm text-red-600">{{ errors.liner_id }}</p>
                            </div>
                        </template>
                    </template>
                    <template v-else>
                        <p class="text-sm text-slate-600">Upload or scan documents to save on this franchise’s ownership record.</p>
                        <Attachments v-model="files" :errors="errors" />
                    </template>
                </fieldset>
                <div class="flex flex-wrap justify-end gap-3 border-t border-[#E3F2FD] pt-5">
                    <button type="button" :disabled="processing" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm text-gray-600 disabled:opacity-50" @click="close">Cancel</button>
                    <button v-if="step > 0" type="button" :disabled="processing" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm text-gray-600 disabled:opacity-50" @click="goToStep(step - 1)">Back</button>
                    <button v-else-if="wizard && !assignOnly" type="button" :disabled="processing" class="rounded-lg border border-blue-200 px-4 py-2.5 text-sm text-[#0D47A1] disabled:opacity-50" @click="saveOwnerOnly">Save owner only</button>
                    <button type="submit" :disabled="processing || (currentStep === 'franchise' && !franchise) || (currentStep === 'vehicle' && (loadingOptions || optionsError))" class="rounded-lg bg-[#2196F3] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#0D47A1] disabled:opacity-50">{{ processing ? 'Saving...' : isLastStep ? (assignOnly ? 'Assign Franchise' : 'Save Owner') : 'Next' }}</button>
                </div>
            </form>
        </dialog>
    </Teleport>
</template>

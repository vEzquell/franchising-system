<script setup>
import { computed, inject, nextTick, ref, watch } from 'vue'
import { ScanLine, Upload, X } from 'lucide-vue-next'
import http from '@/lib/http'
import FranchiseSelect from './FranchiseSelect.vue'
import ScannerCapture from './ScannerCapture.vue'

const open = defineModel('open', { type: Boolean, default: false })
const props = defineProps({ initialFranchise: { type: Object, default: null }, transactionTypes: { type: Array, required: true } })
const emit = defineEmits(['created'])
const route = inject('route')
const dialog = ref(null)
const stepPanel = ref(null)
const step = ref(1)
const franchise = ref(null)
const selectedCodes = ref([])
const selectedTypes = computed(() => props.transactionTypes.filter(type => selectedCodes.value.includes(type.value)))
const files = ref({})
const fileErrors = ref({})
const errors = ref({})
const errorMessage = ref('')
const saving = ref(false)
const scanOpen = ref(false)
const scannedRequirement = ref(null)
const stepLabels = ['Transaction types', 'Franchise', 'Required attachments']
const acceptedTypes = ['application/pdf', 'image/jpeg', 'image/png']
const maxFileSize = 5 * 1024 * 1024
const maxTotalSize = 6 * 1024 * 1024
const typeErrors = computed(() => Object.entries(errors.value)
    .filter(([field]) => /^filings\.\d+\.transaction_type$/.test(field)).map(([, message]) => message))
const selectedRequirements = computed(() => selectedTypes.value.flatMap(type => type.requirements))

watch(open, async value => {
    await nextTick()
    if (value) {
        step.value = 1
        franchise.value = props.initialFranchise
        selectedCodes.value = []
        files.value = {}
        fileErrors.value = {}
        errors.value = {}
        errorMessage.value = ''
        if (!dialog.value.open) dialog.value.showModal()
        await nextTick()
        stepPanel.value?.querySelector('input')?.focus()
    } else {
        scanOpen.value = false
        dialog.value.close()
    }
})

function close() {
    if (!saving.value) open.value = false
}

async function goToStep(destination) {
    step.value = destination
    await nextTick()
    stepPanel.value?.focus()
}

function setFile(requirementId, file) {
    const otherFiles = selectedRequirements.value
        .filter(requirement => requirement.id !== requirementId)
        .reduce((total, requirement) => total + (files.value[requirement.id]?.size ?? 0), 0)

    if (!acceptedTypes.includes(file.type)) {
        fileErrors.value[requirementId] = 'Choose a PDF, JPG, or PNG file.'
    } else if (file.size > maxFileSize) {
        fileErrors.value[requirementId] = 'Each file must be 5 MB or smaller.'
    } else if (file.size + otherFiles > maxTotalSize) {
        fileErrors.value[requirementId] = 'Attachments must total no more than 6 MB per submission.'
    } else {
        files.value[requirementId] = file
        delete fileErrors.value[requirementId]
        return true
    }

    return false
}

function upload(event, requirementId) {
    const file = event.target.files[0]
    if (file) setFile(requirementId, file)
    event.target.value = ''
}

function remove(requirementId) {
    delete files.value[requirementId]
    delete fileErrors.value[requirementId]
}

function startScan(requirementId) {
    scannedRequirement.value = requirementId
    delete fileErrors.value[requirementId]
    scanOpen.value = true
}

function saveScan([file]) {
    if (file && setFile(scannedRequirement.value, file)) scanOpen.value = false
}

function attachmentError(typeIndex, index) {
    return errors.value[`filings.${typeIndex}.attachments.${index}.file`]
        ?? errors.value[`filings.${typeIndex}.attachments.${index}.requirement_id`]
}

async function submit() {
    if (saving.value || !selectedTypes.value.length) return
    if (step.value === 1 || (step.value === 2 && franchise.value)) {
        await goToStep(step.value + 1)
        return
    }
    if (!franchise.value) return

    errors.value = {}
    errorMessage.value = ''
    const missing = selectedRequirements.value.filter(requirement => !files.value[requirement.id])
    if (missing.length) {
        for (const requirement of missing) fileErrors.value[requirement.id] = 'Attach this required document.'
        return
    }
    if (selectedRequirements.value.reduce((total, requirement) => total + files.value[requirement.id].size, 0) > maxTotalSize) {
        errorMessage.value = 'Attachments must total no more than 6 MB per submission.'
        return
    }

    const payload = new FormData()
    payload.append('franchise_id', franchise.value.id)
    selectedTypes.value.forEach((type, typeIndex) => {
        const prefix = `filings[${typeIndex}]`
        payload.append(`${prefix}[transaction_type]`, type.value)
        type.requirements.forEach((requirement, index) => {
            const attachment = `${prefix}[attachments][${index}]`
            payload.append(`${attachment}[requirement_id]`, requirement.id)
            payload.append(`${attachment}[file]`, files.value[requirement.id])
        })
    })

    saving.value = true
    try {
        const { data } = await http.post(route('transactions.file'), payload)
        open.value = false
        emit('created', data.count)
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = Object.fromEntries(Object.entries(error.response.data.errors ?? {}).map(([field, messages]) => [field, messages[0]]))
            if (errors.value.franchise_id) await goToStep(2)
            else if (typeErrors.value.length || Object.keys(errors.value).some(field => /^filings\.\d+$/.test(field))) await goToStep(1)
            errorMessage.value = errors.value.filings ?? 'Check the highlighted fields and try again.'
        } else if (error.response?.status === 413) {
            errorMessage.value = 'The upload is too large. Use smaller files and try again.'
        } else {
            errorMessage.value = 'Unable to submit the transactions. Please try again.'
        }
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <Teleport to="body">
        <dialog ref="dialog" aria-labelledby="transaction-flow-title" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl backdrop:bg-black/50" @cancel.prevent="close" @close="open = false">
            <form class="space-y-4" @submit.prevent="submit">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs text-gray-500">Step {{ step }} of {{ stepLabels.length }}</p>
                        <h2 id="transaction-flow-title" class="text-lg font-bold text-[#0D47A1]">Add Transaction</h2>
                    </div>
                    <button type="button" :disabled="saving" aria-label="Close transaction" class="rounded-lg p-2 hover:bg-blue-50 disabled:opacity-50" @click="close"><X class="h-5 w-5" /></button>
                </div>

                <p v-if="errorMessage" role="alert" class="text-sm text-red-600">{{ errorMessage }}</p>
                <fieldset ref="stepPanel" tabindex="-1" :disabled="saving" class="space-y-3">
                    <legend class="mb-2 text-sm font-semibold text-gray-800">{{ stepLabels[step - 1] }}</legend>
                    <template v-if="step === 1">
                        <label v-for="type in transactionTypes" :key="type.value" class="flex cursor-pointer items-center gap-3 rounded-lg border border-blue-100 px-3 py-2.5 text-sm hover:bg-blue-50">
                            <input v-model="selectedCodes" type="checkbox" :value="type.value" class="size-4 accent-[#0D47A1]" />
                            {{ type.label }}
                        </label>
                        <p v-if="!transactionTypes.length" class="text-sm text-gray-600">There are no active transaction types available for filing.</p>
                        <p v-for="(message, index) in typeErrors" :key="index" role="alert" class="text-sm text-red-600">{{ message }}</p>
                    </template>
                    <FranchiseSelect v-else-if="step === 2 && open" v-model="franchise" :error="errors.franchise_id" />
                    <template v-else-if="step === 3">
                        <div v-for="(type, typeIndex) in selectedTypes" :key="type.value" class="space-y-3 rounded-lg border border-blue-100 p-4">
                            <h3 class="text-sm font-semibold text-[#0D47A1]">{{ type.label }}</h3>
                            <p v-if="!type.requirements.length" class="text-sm text-gray-600">No requirements are configured for this type.</p>
                            <div v-for="(requirement, index) in type.requirements" :key="requirement.id" class="space-y-2 border-t border-blue-50 pt-3 text-sm">
                                <p class="font-medium text-gray-800">{{ requirement.name }}</p>
                                <p v-if="requirement.description" class="whitespace-pre-line text-gray-600">{{ requirement.description }}</p>
                                <div class="flex flex-wrap items-center gap-2">
                                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-blue-200 px-3 py-1.5 text-[#0D47A1] focus-within:ring-2 focus-within:ring-blue-400">
                                        <Upload aria-hidden="true" class="h-4 w-4" />
                                        {{ files[requirement.id] ? 'Replace file' : 'Attach file' }}
                                        <input :aria-label="`Attach ${requirement.name} for ${type.label}`" type="file" accept=".pdf,.jpg,.jpeg,.png" class="sr-only" @change="upload($event, requirement.id)" />
                                    </label>
                                    <button type="button" :aria-label="`Scan ${requirement.name} for ${type.label}`" class="inline-flex items-center gap-2 rounded-lg border border-blue-200 px-3 py-1.5 text-[#0D47A1]" @click="startScan(requirement.id)">
                                        <ScanLine aria-hidden="true" class="h-4 w-4" /> Scan
                                    </button>
                                </div>
                                <div v-if="files[requirement.id]" class="flex items-start justify-between gap-3 text-xs text-gray-600">
                                    <span class="break-all">{{ files[requirement.id].name }}</span>
                                    <button type="button" class="text-red-700 underline" :aria-label="`Remove ${requirement.name} for ${type.label}`" @click="remove(requirement.id)">Remove</button>
                                </div>
                                <p v-if="fileErrors[requirement.id] || attachmentError(typeIndex, index)" role="alert" class="text-xs text-red-600">
                                    {{ fileErrors[requirement.id] || attachmentError(typeIndex, index) }}
                                </p>
                            </div>
                            <p v-if="errors[`filings.${typeIndex}.attachments`]" role="alert" class="text-sm text-red-600">{{ errors[`filings.${typeIndex}.attachments`] }}</p>
                        </div>
                        <p v-if="selectedRequirements.length" class="text-xs text-gray-500">PDF, JPG, or PNG. Up to 5 MB per file and 6 MB per submission.</p>
                        <p class="text-xs text-gray-500">Each selected type is filed as a pending transaction. Its documents stay with that transaction for review.</p>
                    </template>
                </fieldset>

                <div class="flex justify-end gap-3 border-t border-blue-50 pt-4">
                    <button type="button" :disabled="saving" class="rounded-lg border px-4 py-2 text-sm disabled:opacity-50" @click="close">Cancel</button>
                    <button v-if="step > 1" type="button" :disabled="saving" class="rounded-lg border px-4 py-2 text-sm disabled:opacity-50" @click="goToStep(step - 1)">Back</button>
                    <button type="submit" :disabled="!selectedTypes.length || saving || (step > 1 && !franchise)" class="rounded-lg bg-[#2196F3] px-4 py-2 text-sm font-semibold text-white hover:bg-[#0D47A1] disabled:opacity-50">
                        {{ saving ? 'Saving...' : step < 3 ? 'Next' : selectedTypes.length > 1 ? 'Submit transactions' : 'Submit transaction' }}
                    </button>
                </div>
            </form>
        </dialog>
        <ScannerCapture v-model:open="scanOpen" :remaining="1" :queue-error="fileErrors[scannedRequirement]" @scanned="saveScan" />
    </Teleport>
</template>

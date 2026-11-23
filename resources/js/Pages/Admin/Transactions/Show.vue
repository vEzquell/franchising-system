<script setup>
import { inject, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { X } from 'lucide-vue-next'
import http from '@/lib/http'
const open = defineModel('open', { type: Boolean, default: false })
const props = defineProps({ transactionId: Number })
const emit = defineEmits(['status-updated'])
const route = inject('route')
const dialog = ref(null)
const transaction = ref(null)
const loading = ref(false)
const errorMessage = ref('')
const nextStatuses = ref([])
const selectedStatus = ref('')
const saving = ref(false)
const statusError = ref('')
const statusLabels = { under_review: 'Under review', approved: 'Approved', rejected: 'Rejected', cancelled: 'Cancelled' }
let request
async function load() {
    request?.abort()
    const current = new AbortController()
    request = current
    loading.value = true
    errorMessage.value = ''
    nextStatuses.value = []
    selectedStatus.value = ''
    try {
        const { data } = await http.get(route('transactions.show', props.transactionId), { signal: current.signal })
        if (!current.signal.aborted) {
            transaction.value = data.transaction
            nextStatuses.value = data.next_statuses
        }
    } catch (error) { if (!current.signal.aborted) errorMessage.value = 'Unable to load transaction.' }
    finally { if (request === current) loading.value = false }
}
async function updateStatus() {
    if (saving.value || !selectedStatus.value) return
    saving.value = true
    statusError.value = ''
    try {
        await http.patch(route('transactions.status.update', props.transactionId), { status: selectedStatus.value })
        emit('status-updated')
        await load()
    } catch (error) {
        statusError.value = error.response?.data?.message ?? 'Unable to update the transaction status.'
    } finally {
        saving.value = false
    }
}
watch(open, async value => {
    await nextTick()
    if (value) { transaction.value = null; statusError.value = ''; dialog.value.showModal(); load() }
    else { request?.abort(); dialog.value.close() }
})
onBeforeUnmount(() => request?.abort())
</script>
<template>
    <Teleport to="body">
        <dialog ref="dialog" aria-labelledby="transaction-details-title" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl backdrop:bg-black/50" @cancel.prevent="!saving && (open = false)" @close="open = false">
            <div class="mb-4 flex items-center justify-between"><h2 id="transaction-details-title" class="text-lg font-bold text-[#0D47A1]">Transaction details</h2><button type="button" :disabled="saving" aria-label="Close transaction details" class="rounded-lg p-2 hover:bg-blue-50" @click="open = false"><X class="h-5 w-5" /></button></div>
            <p v-if="loading" role="status">Loading...</p><p v-if="errorMessage" role="alert" class="text-sm text-red-600">{{ errorMessage }} <button type="button" class="underline" @click="load">Retry</button></p>
            <div v-if="transaction" class="space-y-4 text-sm">
                <p><strong>Franchise:</strong> {{ transaction.franchise?.franchise_number }}</p><p><strong>Type:</strong> {{ transaction.type?.name ?? transaction.transaction_type }}</p><p class="capitalize"><strong>Status:</strong> {{ transaction.status.replaceAll('_', ' ') }}</p>
                <p><strong>Created by:</strong> {{ transaction.creator?.name ?? 'Unknown' }}</p>
                <p><strong>Processed by:</strong> {{ transaction.processor?.name ?? '—' }}</p>
                <p><strong>Approved by:</strong> {{ transaction.approver?.name ?? (transaction.status === 'approved' ? 'Unknown' : '—') }}</p>
                <p v-if="transaction.remarks" class="whitespace-pre-line break-words"><strong>Remarks:</strong> {{ transaction.remarks }}</p>
                <p v-if="transaction.certificate_issue_date"><strong>Requested certificate issue date</strong> {{ transaction.certificate_issue_date }}</p>
                <p v-if="transaction.requirements_only && ['pending', 'under_review'].includes(transaction.status)" class="rounded-lg bg-blue-50 p-3 text-slate-700">Filed documents are available below. Approval is handled separately.</p>
                <form v-if="nextStatuses.length" class="space-y-2 rounded-lg border border-blue-100 p-3" @submit.prevent="updateStatus">
                    <label for="next-transaction-status" class="block font-semibold">Change status</label>
                    <div class="flex flex-wrap gap-2">
                        <select id="next-transaction-status" v-model="selectedStatus" :disabled="saving" class="rounded-lg border border-[#90CAF9] px-3 py-2">
                            <option value="" disabled>Select a status</option>
                            <option v-for="status in nextStatuses" :key="status" :value="status">{{ statusLabels[status] }}</option>
                        </select>
                        <button type="submit" :disabled="saving || !selectedStatus" class="rounded-lg bg-[#0D47A1] px-3 py-2 font-semibold text-white disabled:opacity-50">{{ saving ? 'Saving...' : 'Update status' }}</button>
                    </div>
                    <p v-if="selectedStatus === 'approved'" class="text-gray-600">
                        Approval updates the franchise certificate dates.
                        <template v-if="transaction.amendment?.transfer">The ownership transfer<template v-if="transaction.substitution"> and vehicle replacement</template> will take effect on the transfer date.</template>
                    </p>
                    <p v-if="statusError" role="alert" class="text-red-600">{{ statusError }}</p>
                </form>
                <div v-if="transaction.vehicle_details"><h3 class="font-semibold">Requested vehicle details</h3><dl class="mt-2 space-y-2"><div v-for="(value, key) in transaction.vehicle_details.vehicle_details" :key="key"><dt class="capitalize text-gray-500">{{ key.replaceAll('_', ' ') }}</dt><dd>{{ value || 'Not set' }}</dd></div></dl></div>
                <div v-if="transaction.renewal"><h3 class="font-semibold">Renewal</h3><p>Previous expiration: {{ transaction.renewal.previous_expiration_date?.slice(0, 10) }}</p><p>Requested expiration: {{ transaction.renewal.new_expiration_date?.slice(0, 10) }}</p></div>
                <div v-if="transaction.substitution"><h3 class="font-semibold">Substitution</h3><p>Previous vehicle: {{ transaction.substitution.previous_vehicle?.plate_number || transaction.substitution.previous_vehicle_id }}</p><p>Replacement: {{ transaction.substitution.new_vehicle?.plate_number || transaction.substitution.new_vehicle_id }}</p><p>{{ transaction.substitution.reason }}</p></div>
                <div v-if="transaction.amendment"><h3 class="font-semibold">Amendment</h3><p>{{ transaction.amendment.reason }}</p><p v-for="change in transaction.amendment.changes" :key="change.id">{{ change.field_name }}: {{ change.old_value || 'Not set' }} ? {{ change.new_value }}</p></div>
                <div><h3 class="mb-2 font-semibold">Attachments</h3><ul class="space-y-2"><li v-for="attachment in transaction.attachments" :key="attachment.id"><a :href="route('transactions.attachments.download', [transaction.id, attachment.id])" class="text-[#0D47A1] underline">{{ attachment.document_type }}: {{ attachment.document_name || attachment.original_name }}</a></li></ul><p v-if="!transaction.attachments.length" class="text-gray-500">No attachments.</p></div>
            </div>
        </dialog>
    </Teleport>
</template>

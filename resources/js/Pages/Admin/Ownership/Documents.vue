<script setup>
import { inject, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { X } from 'lucide-vue-next'
import Attachments from '../Transactions/Attachments.vue'
import DocumentPreview from './DocumentPreview.vue'
import { formatOwnerName } from '@/lib/formatOwnerName'
import http from '@/lib/http'
const open = defineModel('open', { type: Boolean, default: false })
const props = defineProps({ ownershipId: Number, mode: { type: String, required: true } })
const emit = defineEmits(['uploaded', 'deleted'])
const route = inject('route')
const dialog = ref(null)
const ownership = ref(null)
const files = ref([])
const errors = ref({})
const errorMessage = ref('')
const loading = ref(false)
const saving = ref(false)
const canDeleteTransactionAttachments = ref(false)
const deleteDialog = ref(null)
const attachmentToDelete = ref(null)
const deleteError = ref('')
const documentPreview = ref(null)
let request
const fullName = owner => formatOwnerName(owner) || 'Unknown owner'
async function load() {
    request?.abort()
    const current = new AbortController()
    request = current
    loading.value = true
    errorMessage.value = ''
    try {
        const { data } = await http.get(route('ownerships.show', props.ownershipId), { signal: current.signal })
        if (!current.signal.aborted) {
            ownership.value = data.ownership
            canDeleteTransactionAttachments.value = data.can_delete_transaction_attachments
        }
    } catch (error) { if (!current.signal.aborted) errorMessage.value = 'Unable to load ownership documents.' }
    finally { if (request === current) loading.value = false }
}
watch(open, async value => {
    await nextTick()
    if (value) {
        ownership.value = null
        files.value = []
        errors.value = {}
        dialog.value.showModal()
        load()
    } else { request?.abort(); documentPreview.value?.close(); deleteDialog.value?.close(); dialog.value.close() }
})
function close() { if (!saving.value) open.value = false }
function previewDocuments(attachment = null) {
    documentPreview.value.show(ownership.value.id, ownership.value.attachments, attachment)
}
async function submit() {
    if (saving.value || !files.value.length) return
    errors.value = {}
    if (files.value.some(item => item.file.size > 5242880) || files.value.reduce((sum, item) => sum + item.file.size, 0) > 6291456) {
        errors.value = { attachments: 'Use files up to 5 MB each and 6 MB total.' }
        return
    }
    saving.value = true
    errorMessage.value = ''
    try {
        const data = new FormData()
        files.value.forEach((item, index) => {
            data.append(`attachments[${index}][document_name]`, item.document_name)
            data.append(`attachments[${index}][file]`, item.file)
        })
        await http.post(route('ownerships.attachments.store', props.ownershipId), data)
        files.value = []
        emit('uploaded')
        open.value = false
    } catch (error) {
        if (error.response?.status === 422) errors.value = Object.fromEntries(Object.entries(error.response.data.errors ?? {}).map(([field, messages]) => [field, messages[0]]))
        else errorMessage.value = 'Unable to upload documents. Refresh the page and try again.'
    } finally { saving.value = false }
}
function confirmDelete(attachment) {
    attachmentToDelete.value = attachment
    deleteError.value = ''
    deleteDialog.value.showModal()
}
function cancelDelete() { if (!saving.value) deleteDialog.value.close() }
async function deleteAttachment() {
    if (saving.value || !attachmentToDelete.value) return
    const { id } = attachmentToDelete.value
    saving.value = true
    deleteError.value = ''
    try {
        await http.delete(route('ownerships.attachments.destroy', [ownership.value.id, id]))
        ownership.value.attachments = ownership.value.attachments.filter(attachment => attachment.id !== id)
        deleteDialog.value.close()
        emit('deleted')
    } catch (error) {
        deleteError.value = error.response?.status === 404
            ? 'This attachment is no longer available. Reopen View attachments to refresh the list.'
            : 'Unable to delete this attachment. Please try again.'
    } finally { saving.value = false }
}
onBeforeUnmount(() => request?.abort())
</script>
<template>
    <Teleport to="body">
        <dialog ref="dialog" aria-labelledby="ownership-documents-title" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl backdrop:bg-black/50" @cancel.prevent="close" @close="open = false">
            <div class="mb-4 flex items-center justify-between"><h2 id="ownership-documents-title" class="text-lg font-bold text-[#0D47A1]">{{ mode === 'add' ? 'Add attachments' : 'View attachments' }}</h2><button type="button" :disabled="saving" aria-label="Close attachments dialog" class="rounded-lg p-2 hover:bg-blue-50" @click="close"><X class="h-5 w-5" /></button></div>
            <p v-if="loading" role="status" class="text-sm text-gray-500">Loading documents...</p>
            <p v-if="errorMessage" role="alert" class="text-sm text-red-600">{{ errorMessage }} <button type="button" :disabled="saving" class="underline" @click="load">Retry</button></p>
            <div v-if="ownership" class="space-y-4 text-sm">
                <div class="rounded-lg bg-blue-50 p-3"><p><strong>Franchise:</strong> {{ ownership.franchise?.franchise_number }}</p><p><strong>Owner:</strong> {{ fullName(ownership.owner) }}</p><p><strong>Period:</strong> {{ ownership.started_at?.slice(0, 10) }} to {{ ownership.ended_at?.slice(0, 10) || 'Present' }}</p></div>
                <div v-if="mode === 'view'">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-semibold">Saved documents</h3>
                        <button v-if="ownership.attachments.length" type="button" :disabled="loading" class="rounded-lg bg-[#0D47A1] px-3 py-2 font-medium text-white hover:bg-blue-900 disabled:opacity-50" @click="previewDocuments()">Preview all as PDF</button>
                    </div>
                    <ul v-if="ownership.attachments.length" class="divide-y divide-blue-100 rounded-lg border border-blue-100">
                        <li v-for="attachment in ownership.attachments" :key="attachment.id" class="flex items-center justify-between gap-3 p-3">
                            <div class="min-w-0">
                                <p class="break-words font-medium">{{ attachment.document_name || attachment.original_name }}</p>
                                <p v-if="attachment.document_type" class="break-words text-xs text-gray-500">{{ attachment.document_type }}</p>
                            </div>
                            <div class="flex shrink-0 flex-wrap justify-end gap-2">
                                <button type="button" :aria-label="`Preview ${attachment.document_name || attachment.original_name}`" class="rounded-lg border border-[#90CAF9] px-3 py-1.5 font-medium text-[#0D47A1] hover:bg-blue-50" @click="previewDocuments(attachment)">Preview</button>
                                <button v-if="attachment.franchise_transaction_id === null || canDeleteTransactionAttachments" type="button" :aria-label="`Delete ${attachment.document_name || attachment.original_name}`" class="rounded-lg bg-red-50 px-3 py-1.5 font-medium text-red-700 hover:bg-red-100" @click="confirmDelete(attachment)">Delete</button>
                            </div>
                        </li>
                    </ul>
                    <p v-if="!ownership.attachments.length" class="text-gray-500">No documents uploaded yet.</p>
                </div>
                <form v-else @submit.prevent="submit">
                    <fieldset :disabled="saving"><Attachments v-if="open" v-model="files" :errors="errors" /></fieldset>
                    <div class="mt-4 flex justify-end gap-3"><button type="button" :disabled="saving" class="rounded-lg border px-4 py-2 text-sm" @click="close">Close</button><button type="submit" :disabled="saving || !files.length" class="rounded-lg bg-[#2196F3] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ saving ? 'Uploading...' : 'Upload documents' }}</button></div>
                </form>
            </div>
        </dialog>
        <dialog ref="deleteDialog" aria-labelledby="attachment-delete-title" aria-describedby="attachment-delete-description" class="fixed inset-0 m-auto w-[calc(100%-2rem)] max-w-md rounded-xl bg-white p-6 shadow-xl backdrop:bg-black/50" @cancel.prevent="cancelDelete">
            <form @submit.prevent="deleteAttachment">
                <h2 id="attachment-delete-title" class="mb-3 text-lg font-bold text-[#0D47A1]">Delete attachment</h2>
                <p id="attachment-delete-description" class="break-words text-sm text-gray-700">Permanently delete “{{ attachmentToDelete?.document_name || attachmentToDelete?.original_name }}”<template v-if="attachmentToDelete?.franchise_transaction_id"> from this ownership record and its linked transaction</template>?</p>
                <p v-if="deleteError" role="alert" class="mt-3 text-sm text-red-600">{{ deleteError }}</p>
                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" autofocus :disabled="saving" class="rounded-lg border px-4 py-2 text-sm disabled:opacity-50" @click="cancelDelete">Cancel</button>
                    <button type="submit" :disabled="saving" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50">{{ saving ? 'Deleting...' : 'Delete attachment' }}</button>
                </div>
            </form>
        </dialog>
        <DocumentPreview ref="documentPreview" />
    </Teleport>
</template>

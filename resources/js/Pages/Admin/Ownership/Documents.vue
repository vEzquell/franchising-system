<script setup>
import { inject, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { X } from 'lucide-vue-next'
import Attachments from '../Transactions/Attachments.vue'
import { combineOwnershipDocuments } from './documents-pdf'
import http from '@/lib/http'
const open = defineModel('open', { type: Boolean, default: false })
const props = defineProps({ ownershipId: Number, mode: { type: String, required: true } })
const emit = defineEmits(['uploaded'])
const route = inject('route')
const dialog = ref(null)
const ownership = ref(null)
const files = ref([])
const errors = ref({})
const errorMessage = ref('')
const loading = ref(false)
const saving = ref(false)
const previewDialog = ref(null)
const previewUrl = ref('')
const previewError = ref('')
const previewTitle = ref('')
const previewFilename = ref('')
const previewMimeType = ref('')
let request
let previewRequest
const fullName = owner => owner ? [owner.first_name, owner.middle_name, owner.last_name, owner.suffix].filter(Boolean).join(' ') : 'Unknown owner'
async function load() {
    request?.abort()
    const current = new AbortController()
    request = current
    loading.value = true
    errorMessage.value = ''
    try {
        const { data } = await http.get(route('ownerships.show', props.ownershipId), { signal: current.signal })
        if (!current.signal.aborted) ownership.value = data.ownership
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
    } else { request?.abort(); closePreview(); dialog.value.close() }
})
function close() { if (!saving.value) open.value = false }
function closePreview() {
    previewRequest?.abort()
    previewDialog.value?.close()
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = ''
}
async function previewDocuments(attachment = null) {
    if (previewDialog.value.open || !ownership.value?.attachments.length) return
    const { id, attachments } = ownership.value
    const current = new AbortController()
    previewRequest = current
    previewError.value = ''
    previewTitle.value = attachment ? attachment.document_name || attachment.original_name : 'Combined ownership documents'
    previewFilename.value = attachment ? attachment.original_name : 'ownership-documents.pdf'
    previewMimeType.value = attachment ? attachment.mime_type : 'application/pdf'
    previewDialog.value.showModal()

    try {
        const readAttachment = async item => {
            const { data } = await http.get(route('ownerships.attachments.download', [id, item.id]), {
                responseType: 'arraybuffer',
                signal: current.signal,
            })
            return data
        }
        if (attachment && !['application/pdf', 'image/jpeg', 'image/png'].includes(attachment.mime_type)) {
            throw new Error('This attachment format cannot be previewed.')
        }
        const document = attachment
            ? new Blob([await readAttachment(attachment)], { type: attachment.mime_type })
            : await combineOwnershipDocuments(attachments, readAttachment, current.signal)
        if (!current.signal.aborted) previewUrl.value = URL.createObjectURL(document)
    } catch (error) {
        if (!current.signal.aborted) previewError.value = attachment && error.isAxiosError ? 'Unable to load this attachment.' : error.message || 'Unable to prepare the preview.'
    }
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
onBeforeUnmount(() => { request?.abort(); closePreview() })
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
                            <button type="button" :aria-label="`Preview ${attachment.document_name || attachment.original_name}`" class="shrink-0 rounded-lg border border-[#90CAF9] px-3 py-1.5 font-medium text-[#0D47A1] hover:bg-blue-50" @click="previewDocuments(attachment)">Preview</button>
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
        <dialog ref="previewDialog" aria-labelledby="ownership-preview-title" class="fixed inset-0 m-auto w-[calc(100%-2rem)] max-w-5xl rounded-xl bg-white p-4 shadow-xl backdrop:bg-black/50" @cancel.prevent="closePreview">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 id="ownership-preview-title" class="min-w-0 break-words font-bold text-[#0D47A1]">{{ previewTitle }}</h2>
                <button type="button" aria-label="Close attachment preview" class="shrink-0 rounded-lg p-2 hover:bg-blue-50" @click="closePreview"><X class="h-5 w-5" /></button>
            </div>
            <template v-if="previewUrl">
                <a :href="previewUrl" :download="previewFilename" class="mb-2 inline-block text-sm text-[#0D47A1] underline">{{ previewMimeType === 'application/pdf' ? 'Download PDF' : 'Download image' }}</a>
                <iframe v-if="previewMimeType === 'application/pdf'" :src="previewUrl" :title="previewTitle" class="h-[min(70dvh,52rem)] w-full rounded-lg border border-blue-100"></iframe>
                <img v-else :src="previewUrl" :alt="previewTitle" class="mx-auto max-h-[70dvh] max-w-full object-contain" />
            </template>
            <p v-else-if="previewError" role="alert" class="text-sm text-red-600">{{ previewError }}</p>
            <p v-else role="status" class="text-sm text-gray-500">Preparing preview...</p>
        </dialog>
    </Teleport>
</template>

<script setup>
import { inject, onBeforeUnmount, ref, useId } from 'vue'
import { Download, X } from 'lucide-vue-next'
import { combineOwnershipDocuments } from './documents-pdf'
import http from '@/lib/http'

const route = inject('route')
const titleId = useId()
const dialog = ref(null)
const url = ref('')
const errorMessage = ref('')
const title = ref('')
const filename = ref('')
const mimeType = ref('')
let request

function close() {
    request?.abort()
    dialog.value?.close()
    if (url.value) URL.revokeObjectURL(url.value)
    url.value = ''
}

async function show(ownershipId, attachments, attachment = null) {
    if (dialog.value.open || !attachments.length) return

    const current = new AbortController()
    request = current
    errorMessage.value = ''
    title.value = attachment ? attachment.document_name || attachment.original_name : 'Combined ownership documents'
    filename.value = attachment ? attachment.original_name : 'ownership-documents.pdf'
    mimeType.value = attachment ? attachment.mime_type : 'application/pdf'
    dialog.value.showModal()

    try {
        const readAttachment = async item => {
            const { data } = await http.get(route('ownerships.attachments.download', [ownershipId, item.id]), {
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
        if (!current.signal.aborted) url.value = URL.createObjectURL(document)
    } catch (error) {
        if (!current.signal.aborted) {
            errorMessage.value = attachment && error.isAxiosError ? 'Unable to load this attachment.' : error.message || 'Unable to prepare the preview.'
        }
    }
}

defineExpose({ show, close })
onBeforeUnmount(close)
</script>

<template>
    <Teleport to="body">
        <dialog ref="dialog" :aria-labelledby="titleId" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-5xl overflow-y-auto rounded-xl bg-white p-4 shadow-xl backdrop:bg-black/50 sm:p-5" @cancel.prevent="close">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 :id="titleId" class="min-w-0 break-words font-bold text-[#0D47A1]">{{ title }}</h2>
                <button type="button" aria-label="Close attachment preview" class="shrink-0 rounded-lg p-2 hover:bg-blue-50" @click="close"><X class="h-5 w-5" /></button>
            </div>
            <template v-if="url">
                <a :href="url" :download="filename" class="mb-3 inline-flex items-center gap-2 rounded-lg border border-blue-200 px-3 py-2 text-sm font-medium text-[#0D47A1] hover:bg-blue-50">
                    <Download aria-hidden="true" class="h-4 w-4" /> {{ mimeType === 'application/pdf' ? 'Download PDF' : 'Download image' }}
                </a>
                <iframe v-if="mimeType === 'application/pdf'" :src="url" :title="title" class="h-[min(70dvh,52rem)] w-full rounded-lg border border-blue-100"></iframe>
                <img v-else :src="url" :alt="title" class="mx-auto max-h-[70dvh] max-w-full object-contain" />
            </template>
            <p v-else-if="errorMessage" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ errorMessage }}</p>
            <p v-else role="status" class="rounded-lg bg-blue-50 p-3 text-sm text-gray-600">Preparing preview...</p>
        </dialog>
    </Teleport>
</template>

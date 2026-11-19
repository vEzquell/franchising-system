<script setup>
import { inject, onBeforeUnmount, onMounted, ref } from 'vue'
import { Eye, Files, FileText } from 'lucide-vue-next'
import DocumentPreview from '../Ownership/DocumentPreview.vue'
import { formatOwnerName } from '@/lib/formatOwnerName'
import http from '@/lib/http'

const props = defineProps({ ownership: { type: Object, required: true } })
const route = inject('route')
const preview = ref(null)
const attachments = ref([])
const loading = ref(true)
const errorMessage = ref('')
let request

async function load() {
    const current = new AbortController()
    request = current
    loading.value = true
    errorMessage.value = ''
    try {
        const { data } = await http.get(route('ownerships.show', props.ownership.id), { signal: current.signal })
        if (!current.signal.aborted) attachments.value = data.ownership.attachments
    } catch (error) {
        if (!current.signal.aborted) errorMessage.value = 'Unable to load this owner’s attachments.'
    } finally {
        if (!current.signal.aborted) loading.value = false
    }
}

function openPreview(attachment = null) {
    preview.value.show(props.ownership.id, attachments.value, attachment)
}

onMounted(load)
onBeforeUnmount(() => request?.abort())
</script>

<template>
    <div class="min-w-0" :aria-busy="loading">
        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
            <h4 class="min-w-0 break-words text-sm font-semibold text-slate-800">{{ formatOwnerName(ownership.owner) || 'Unknown owner' }}</h4>
            <button v-if="attachments.length > 1 && !loading" type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 px-2.5 py-1.5 text-xs font-semibold text-[#0D47A1] hover:bg-blue-50" @click="openPreview()">
                <Files aria-hidden="true" class="h-4 w-4" /> Preview all as PDF
            </button>
        </div>
        <p v-if="loading" role="status" class="px-4 pb-4 text-sm text-slate-600">Loading attachments...</p>
        <p v-else-if="errorMessage" role="alert" class="px-4 pb-4 text-sm text-red-700">{{ errorMessage }} <button type="button" class="font-semibold underline" @click="load">Retry</button></p>
        <ul v-else-if="attachments.length" class="mx-4 mb-4 divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-200">
            <li v-for="attachment in attachments" :key="attachment.id" class="flex items-center gap-3 px-3 py-2.5">
                <FileText aria-hidden="true" class="hidden h-4 w-4 shrink-0 text-blue-600 sm:block" />
                <div class="min-w-0 flex-1">
                    <p class="break-words text-sm font-medium text-slate-800">{{ attachment.document_name || attachment.original_name }}</p>
                    <p v-if="attachment.document_name && attachment.document_name !== attachment.original_name" class="break-all text-xs text-slate-500">{{ attachment.original_name }}</p>
                </div>
                <button type="button" :aria-label="`Preview ${attachment.document_name || attachment.original_name}`" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-blue-50 px-2.5 py-2 text-xs font-semibold text-[#0D47A1] hover:bg-blue-100" @click="openPreview(attachment)">
                    <Eye aria-hidden="true" class="h-4 w-4" /> Preview
                </button>
            </li>
        </ul>
        <p v-else class="px-4 pb-4 text-sm text-slate-600">No attachments for this owner.</p>
        <DocumentPreview ref="preview" />
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Upload, ScanLine } from 'lucide-vue-next'
import ScannerCapture from './ScannerCapture.vue'
const files = defineModel({ type: Array, default: () => [] })
defineProps({ errors: { type: Object, default: () => ({}) } })
const name = ref('')
const input = ref(null)
const scanOpen = ref(false)
const formError = ref('')
const queuedSize = computed(() => files.value.reduce((total, item) => total + item.file.size, 0))
function metadataValid() {
    formError.value = ''
    if (!name.value.trim()) { formError.value = 'Enter the document name first.'; return false }
    return true
}
function upload() { if (metadataValid()) input.value.click() }
function scan() { if (metadataValid()) scanOpen.value = true }
function add(selected) {
    formError.value = ''
    if (files.value.length + selected.length > 10) { formError.value = 'Maximum 10 attachments. Remove some files or select fewer pages.'; return }
    if (selected.some(file => file.size > 5242880)) { formError.value = 'Each attachment must be no larger than 5 MB.'; return }
    if (selected.some(file => !['application/pdf', 'image/jpeg', 'image/png'].includes(file.type))) { formError.value = 'Choose PDF, JPG, or PNG files.'; return }
    if (queuedSize.value + selected.reduce((total, file) => total + file.size, 0) > 6291456) { formError.value = 'Attachments must total no more than 6 MB.'; return }
    for (const file of selected) files.value.push({ file, document_name: `${name.value.trim()}${selected.length > 1 ? ` - ${file.name}` : ''}`.slice(0, 255) })
}
function addUpload(event) { add(Array.from(event.target.files)); event.target.value = '' }
</script>
<template>
    <div class="mt-4 space-y-3 rounded-lg border border-blue-100 p-4">
        <h3 class="font-semibold text-[#0D47A1]">Attachments</h3>
        <div><label for="attachment-document-name" class="mb-2 block text-sm font-medium">Document name</label><input v-uppercase id="attachment-document-name" v-model="name" maxlength="255" placeholder="e.g. 2026 franchise certificate" class="w-full rounded-lg border border-[#90CAF9] px-3 py-2 text-sm" /></div>
        <div class="flex flex-wrap gap-3"><button type="button" :disabled="files.length >= 10" class="inline-flex items-center gap-2 rounded-lg border border-[#90CAF9] px-4 py-2 text-sm text-[#0D47A1] disabled:opacity-50" @click="upload"><Upload class="h-4 w-4" /> Upload files</button><button type="button" :disabled="files.length >= 10" class="inline-flex items-center gap-2 rounded-lg bg-[#2196F3] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" @click="scan"><ScanLine class="h-4 w-4" /> Scan documents</button></div>
        <input ref="input" type="file" multiple accept=".pdf,.jpg,.jpeg,.png" class="hidden" @change="addUpload" />
        <p class="text-xs text-gray-500">PDF, JPG, or PNG, up to 5 MB each; maximum 10 files and 6 MB total. Uploads and scans are saved only when you submit.</p>
        <p v-if="formError" role="alert" class="text-sm text-red-600">{{ formError }}</p>
        <p v-if="errors.attachments" role="alert" class="text-sm text-red-600">{{ errors.attachments }}</p>
        <div v-for="(item, index) in files" :key="index" class="space-y-2 border-t border-blue-50 pt-3">
            <p class="break-words text-xs text-gray-500">{{ item.file.name }} ({{ Math.ceil(item.file.size / 1024) }} KB)</p>
            <label :for="`queued-name-${index}`" class="block text-sm">Document name</label><input v-uppercase :id="`queued-name-${index}`" v-model="item.document_name" required maxlength="255" class="w-full rounded-lg border border-[#90CAF9] px-3 py-2 text-sm" />
            <p v-for="key in [`attachments.${index}.file`, `attachments.${index}.document_name`]" :key="key" role="alert" class="text-sm text-red-600">{{ errors[key] }}</p>
            <button type="button" class="text-sm text-red-700 underline" @click="files.splice(index, 1)">Remove</button>
        </div>
        <p v-if="scanOpen && formError" role="alert" class="text-sm text-red-600">{{ formError }}</p>
        <ScannerCapture v-model:open="scanOpen" :remaining="10 - files.length" :queue-error="formError" @scanned="add" />
    </div>
</template>

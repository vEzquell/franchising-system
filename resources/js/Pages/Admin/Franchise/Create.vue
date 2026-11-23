<script setup>
import { inject, nextTick, reactive, ref, watch } from 'vue'
import { FileText, X } from 'lucide-vue-next'
import http from '@/lib/http'

const open = defineModel('open', { type: Boolean, default: false })
const emit = defineEmits(['created'])
const route = inject('route')
const dialog = ref(null)
const numberInput = ref(null)
const processing = ref(false)
const errors = ref({})
const errorMessage = ref('')
const defaults = () => ({ franchise_number: '', status: 'active', date_issued: '', expiration_date: '' })
const form = reactive(defaults())
watch(open, async value => {
    await nextTick()
    if (value) {
        Object.assign(form, defaults())
        errors.value = {}
        errorMessage.value = ''
        if (!dialog.value.open) dialog.value.showModal()
        numberInput.value?.focus()
    } else dialog.value.close()
})
function close() {
    if (!processing.value) open.value = false
}
function closeOnBackdrop(event) {
    if (event.target !== dialog.value) return
    const bounds = dialog.value.getBoundingClientRect()
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) close()
}
async function submit() {
    if (processing.value) return
    processing.value = true
    errors.value = {}
    errorMessage.value = ''
    try {
        await http.post(route('franchise.store'), {
            ...form,
            date_issued: form.date_issued || null,
            expiration_date: form.expiration_date || null,
        })
        open.value = false
        emit('created')
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = Object.fromEntries(Object.entries(error.response.data.errors ?? {}).map(([field, messages]) => [field, messages[0]]))
        } else {
            errorMessage.value = error.response?.status === 403 ? 'You do not have permission to add franchises.'
                : [401, 419].includes(error.response?.status) ? 'Your session ended. Refresh the page and sign in again.'
                    : 'Unable to create the franchise. Please try again.'
        }
    } finally {
        processing.value = false
    }
}
</script>

<template>
    <Teleport to="body">
        <dialog ref="dialog" aria-labelledby="create-franchise-title" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-md overflow-y-auto rounded-xl border border-[#90CAF9]/50 bg-white p-0 shadow-2xl backdrop:bg-black/50" @cancel.prevent="close" @close="open = false" @click="closeOnBackdrop">
            <div class="flex items-center justify-between gap-4 border-b border-[#E3F2FD] p-5">
                <div class="flex items-center gap-2">
                    <FileText class="h-6 w-6 text-[#0D47A1]" />
                    <h2 id="create-franchise-title" class="text-xl font-bold text-[#0D47A1]">Add Franchise</h2>
                </div>
                <button type="button" aria-label="Close Add Franchise" :disabled="processing" class="rounded-lg p-2 text-gray-500 hover:bg-blue-50 disabled:opacity-50" @click="close"><X class="h-5 w-5" /></button>
            </div>
            <form class="space-y-5 p-5" @submit.prevent="submit">
                <p v-if="errorMessage" role="alert" class="text-sm text-red-600">{{ errorMessage }}</p>
                <div>
                    <label for="create-franchise-number" class="mb-2 block text-sm font-medium text-gray-700">Franchise number <span aria-hidden="true" class="text-red-600">*</span></label>
                    <input v-uppercase id="create-franchise-number" ref="numberInput" v-model="form.franchise_number" required maxlength="50" :disabled="processing" :aria-invalid="!!errors.franchise_number" aria-describedby="create-franchise-number-error" class="w-full rounded-lg border border-[#90CAF9] px-3 py-2.5 text-sm focus:outline-[#2196F3]" />
                    <p id="create-franchise-number-error" role="alert" class="mt-1 text-sm text-red-600">{{ errors.franchise_number }}</p>
                </div>
                <div>
                    <label for="create-franchise-status" class="mb-2 block text-sm font-medium text-gray-700">Status</label>
                    <select id="create-franchise-status" v-model="form.status" :disabled="processing" :aria-invalid="!!errors.status" aria-describedby="create-franchise-status-error" class="w-full rounded-lg border border-[#90CAF9] bg-white px-3 py-2.5 text-sm">
                        <option value="active">Active</option>
                        <option value="expired">Expired</option>
                        <option value="suspended">Suspended</option>
                        <option value="unavailable">Unavailable</option>
                    </select>
                    <p id="create-franchise-status-error" role="alert" class="mt-1 text-sm text-red-600">{{ errors.status }}</p>
                </div>
                <div v-for="field in ['date_issued', 'expiration_date']" :key="field">
                    <label :for="`create-${field}`" class="mb-2 block text-sm font-medium text-gray-700">{{ field === 'date_issued' ? 'Date issued' : 'Expiration date' }}</label>
                    <input :id="`create-${field}`" v-model="form[field]" type="date" :disabled="processing" :aria-invalid="!!errors[field]" :aria-describedby="`create-${field}-error`" :min="field === 'expiration_date' ? form.date_issued : undefined" class="w-full rounded-lg border border-[#90CAF9] px-3 py-2.5 text-sm" />
                    <p :id="`create-${field}-error`" role="alert" class="mt-1 text-sm text-red-600">{{ errors[field] }}</p>
                </div>
                <div class="flex justify-end gap-3 border-t border-[#E3F2FD] pt-5">
                    <button type="button" :disabled="processing" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm text-gray-600 disabled:opacity-50" @click="close">Cancel</button>
                    <button type="submit" :disabled="processing" class="rounded-lg bg-[#2196F3] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#0D47A1] disabled:opacity-50">{{ processing ? 'Saving...' : 'Save Franchise' }}</button>
                </div>
            </form>
        </dialog>
    </Teleport>
</template>

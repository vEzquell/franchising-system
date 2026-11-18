<script setup>
import { computed, inject, nextTick, reactive, ref, watch } from 'vue'
import { Users, X } from 'lucide-vue-next'
import http from '@/lib/http'

const open = defineModel('open', { type: Boolean, default: false })
const props = defineProps({ owner: { type: Object, default: null } })
const emit = defineEmits(['saved'])
const route = inject('route')
const dialog = ref(null)
const firstInput = ref(null)
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
const title = computed(() => props.owner ? 'Edit Owner' : 'Add Owner')
watch(open, async value => {
    await nextTick()
    if (value) {
        for (const field of fields) form[field.key] = props.owner?.[field.key] ?? ''
        errors.value = {}
        errorMessage.value = ''
        if (!dialog.value.open) dialog.value.showModal()
        firstInput.value?.[0]?.focus()
    } else dialog.value.close()
})
function close() {
    if (!processing.value) open.value = false
}
async function submit() {
    if (processing.value) return
    processing.value = true
    errors.value = {}
    errorMessage.value = ''
    try {
        const { data } = await http.post(props.owner ? route('owners.update', props.owner.id) : route('owners.store'), { ...form })
        open.value = false
        emit('saved', data.message, data.owner)
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = Object.fromEntries(Object.entries(error.response.data.errors ?? {}).map(([field, messages]) => [field, messages[0]]))
        } else errorMessage.value = error.response?.status === 403 ? 'You do not have permission to manage owners.'
            : [401, 419].includes(error.response?.status) ? 'Your session ended. Refresh the page and sign in again.' : 'Unable to save the owner. Please try again.'
    } finally {
        processing.value = false
    }
}
</script>
<template>
    <Teleport to="body">
        <dialog ref="dialog" aria-labelledby="owner-form-title" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-md overflow-y-auto rounded-xl border border-[#90CAF9]/50 bg-white p-0 shadow-2xl backdrop:bg-black/50" @cancel.prevent="close" @close="open = false">
            <div class="flex items-center justify-between gap-4 border-b border-[#E3F2FD] p-5">
                <div class="flex items-center gap-2"><Users class="h-6 w-6 text-[#0D47A1]" /><h2 id="owner-form-title" class="text-xl font-bold text-[#0D47A1]">{{ title }}</h2></div>
                <button type="button" aria-label="Close owner form" :disabled="processing" class="rounded-lg p-2 text-gray-500 hover:bg-blue-50 disabled:opacity-50" @click="close"><X class="h-5 w-5" /></button>
            </div>
            <form class="space-y-4 p-5" @submit.prevent="submit">
                <p v-if="errorMessage" role="alert" class="text-sm text-red-600">{{ errorMessage }}</p>
                <div v-for="field in fields" :key="field.key">
                    <label :for="`owner-${field.key}`" class="mb-2 block text-sm font-medium text-gray-700">{{ field.label }} <span v-if="field.required" aria-hidden="true" class="text-red-600">*</span></label>
                    <input :id="`owner-${field.key}`" ref="firstInput" v-model="form[field.key]" :type="field.type ?? 'text'" :required="field.required" :maxlength="field.max" :disabled="processing" :aria-invalid="!!errors[field.key]" :aria-describedby="`owner-${field.key}-error`" class="w-full rounded-lg border border-[#90CAF9] px-3 py-2.5 text-sm focus:outline-[#2196F3]" />
                    <p :id="`owner-${field.key}-error`" role="alert" class="mt-1 text-sm text-red-600">{{ errors[field.key] }}</p>
                </div>
                <div class="flex justify-end gap-3 border-t border-[#E3F2FD] pt-5">
                    <button type="button" :disabled="processing" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm text-gray-600 disabled:opacity-50" @click="close">Cancel</button>
                    <button type="submit" :disabled="processing" class="rounded-lg bg-[#2196F3] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#0D47A1] disabled:opacity-50">{{ processing ? 'Saving...' : 'Save Owner' }}</button>
                </div>
            </form>
        </dialog>
    </Teleport>
</template>

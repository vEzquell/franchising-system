<script setup>
import { computed, inject, reactive, ref, watch } from 'vue'
import { Route, X } from 'lucide-vue-next'
import http from '@/lib/http'

const open = defineModel('open', { type: Boolean, default: false })
const props = defineProps({ liner: { type: Object, default: null } })
const emit = defineEmits(['saved'])

const route = inject('route')
const dialog = ref(null)
const form = reactive({ name: '', route: '' })
const errors = ref({})
const errorMessage = ref('')
const processing = ref(false)
const title = computed(() => props.liner ? 'Edit Liner' : 'Add Liner')
const fields = [
    { key: 'name', label: 'Liner name' },
    { key: 'route', label: 'Route' },
]

watch(open, isOpen => {
    if (isOpen) {
        form.name = props.liner?.name ?? ''
        form.route = props.liner?.route ?? ''
        errors.value = {}
        errorMessage.value = ''
        dialog.value.showModal()
    } else {
        dialog.value.close()
    }
}, { flush: 'post' })

function close() {
    if (!processing.value) {
        open.value = false
    }
}

async function submit() {
    if (processing.value) return

    processing.value = true
    errors.value = {}
    errorMessage.value = ''

    try {
        const url = props.liner ? route('liners.update', props.liner.id) : route('liners.store')
        const { data } = await http.post(url, { ...form })
        open.value = false
        emit('saved', data.message)
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors ?? {}
        } else {
            errorMessage.value = error.response?.status === 403
                ? 'You do not have permission to manage liners.'
                : 'Unable to save the liner. Please try again.'
        }
    } finally {
        processing.value = false
    }
}
</script>

<template>
    <Teleport to="body">
        <dialog ref="dialog" aria-labelledby="liner-form-title"
            class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-md overflow-y-auto rounded-xl border border-[#90CAF9]/50 bg-white p-0 shadow-2xl backdrop:bg-black/50"
            @cancel.prevent="close" @close="open = false">
            <div class="flex items-center justify-between gap-4 border-b border-[#E3F2FD] p-5">
                <div class="flex items-center gap-2">
                    <Route aria-hidden="true" class="h-6 w-6 text-[#0D47A1]" />
                    <h2 id="liner-form-title" class="text-xl font-bold text-[#0D47A1]">{{ title }}</h2>
                </div>
                <button type="button" aria-label="Close liner form" :disabled="processing"
                    class="rounded-lg p-2 text-gray-500 hover:bg-blue-50 disabled:opacity-50" @click="close">
                    <X aria-hidden="true" class="h-5 w-5" />
                </button>
            </div>

            <form class="space-y-4 p-5" @submit.prevent="submit">
                <p v-if="errorMessage" role="alert" class="text-sm text-red-600">{{ errorMessage }}</p>
                <div v-for="field in fields" :key="field.key">
                    <label :for="`liner-${field.key}`" class="mb-2 block text-sm font-medium text-gray-700">{{ field.label }}</label>
                    <input v-uppercase :id="`liner-${field.key}`" v-model="form[field.key]" type="text" maxlength="191" required
                        :autofocus="field.key === 'name'" :disabled="processing" :aria-invalid="!!errors[field.key]"
                        :aria-describedby="errors[field.key] ? `liner-${field.key}-error` : undefined"
                        class="w-full rounded-lg border border-[#90CAF9] px-3 py-2.5 text-sm focus:outline-[#2196F3]" />
                    <p v-if="errors[field.key]" :id="`liner-${field.key}-error`" role="alert" class="mt-1 text-sm text-red-600">{{ errors[field.key][0] }}</p>
                </div>
                <div class="flex justify-end gap-3 border-t border-[#E3F2FD] pt-5">
                    <button type="button" :disabled="processing"
                        class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm text-gray-600 disabled:opacity-50" @click="close">
                        Cancel
                    </button>
                    <button type="submit" :disabled="processing"
                        class="rounded-lg bg-[#2196F3] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#0D47A1] disabled:opacity-50">
                        {{ processing ? 'Saving...' : 'Save Liner' }}
                    </button>
                </div>
            </form>
        </dialog>
    </Teleport>
</template>

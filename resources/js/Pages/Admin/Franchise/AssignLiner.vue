<script setup>
import { computed, inject, onBeforeUnmount, ref, watch } from 'vue'
import { X } from 'lucide-vue-next'
import http from '@/lib/http'

const open = defineModel('open', { type: Boolean, default: false })
const props = defineProps({ franchise: { type: Object, default: null } })
const emit = defineEmits(['saved'])
const route = inject('route')
const dialog = ref(null)
const liners = ref([])
const currentLinerId = ref(null)
const selectedLinerId = ref(null)
const loading = ref(false)
const saving = ref(false)
const loadError = ref(false)
const saveError = ref('')
const linerError = ref('')
const canSave = computed(() => !loading.value && !loadError.value && !saving.value && selectedLinerId.value !== currentLinerId.value)
let optionsRequest

async function loadLiners() {
    optionsRequest?.abort()
    const request = new AbortController()
    optionsRequest = request
    loading.value = true
    loadError.value = false

    try {
        const { data } = await http.get(route('franchise.liner', props.franchise.id), { signal: request.signal })
        if (request.signal.aborted) return

        liners.value = data.liners
        currentLinerId.value = data.liner_id
        selectedLinerId.value = data.liner_id
    } catch {
        if (!request.signal.aborted) loadError.value = true
    } finally {
        if (optionsRequest === request) loading.value = false
    }
}

watch(open, isOpen => {
    if (isOpen) {
        liners.value = []
        currentLinerId.value = null
        selectedLinerId.value = null
        linerError.value = ''
        saveError.value = ''
        dialog.value.showModal()
        loadLiners()
    } else {
        optionsRequest?.abort()
        dialog.value.close()
    }
}, { flush: 'post' })

function close() {
    if (!saving.value) open.value = false
}

async function save() {
    if (!canSave.value) return

    saving.value = true
    linerError.value = ''
    saveError.value = ''

    try {
        const { data } = await http.patch(route('franchise.liner.update', props.franchise.id), {
            liner_id: selectedLinerId.value,
        })
        open.value = false
        emit('saved', data.message)
    } catch (error) {
        if (error.response?.status === 422) {
            linerError.value = error.response.data.errors?.liner_id?.[0] ?? 'Select an available liner.'
        } else {
            saveError.value = 'Unable to save the liner link. Please try again.'
        }
    } finally {
        saving.value = false
    }
}

onBeforeUnmount(() => optionsRequest?.abort())
</script>

<template>
    <Teleport to="body">
        <dialog ref="dialog" aria-labelledby="assign-liner-title"
            class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl backdrop:bg-black/50"
            @cancel.prevent="close" @close="open = false">
            <form class="space-y-4" @submit.prevent="save">
                <div class="flex items-center justify-between gap-4">
                    <h2 id="assign-liner-title" class="text-lg font-bold text-[#0D47A1]">Link Liner</h2>
                    <button type="button" aria-label="Close liner assignment" :disabled="saving"
                        class="rounded-lg p-2 hover:bg-blue-50 disabled:opacity-50" @click="close">
                        <X aria-hidden="true" class="h-5 w-5" />
                    </button>
                </div>
                <p class="text-sm text-gray-600">Franchise <strong class="text-gray-800">{{ franchise?.franchise_number }}</strong></p>
                <p v-if="loading" role="status" class="text-sm text-gray-500">Loading liners...</p>
                <p v-else-if="loadError" role="alert" class="text-sm text-red-600">
                    Unable to load liners.
                    <button type="button" class="underline" @click="loadLiners">Retry</button>
                </p>
                <div v-else-if="liners.length">
                    <label for="franchise-liner" class="mb-2 block text-sm font-medium text-gray-700">Liner</label>
                    <select id="franchise-liner" v-model="selectedLinerId" :disabled="saving"
                        :aria-invalid="!!linerError" :aria-describedby="linerError ? 'franchise-liner-error' : undefined"
                        class="w-full rounded-lg border border-[#90CAF9] px-3 py-2.5 text-sm focus:outline-[#2196F3]">
                        <option :value="null">No liner linked</option>
                        <option v-for="liner in liners" :key="liner.id" :value="liner.id">{{ liner.name }} — {{ liner.route }}</option>
                    </select>
                    <p v-if="linerError" id="franchise-liner-error" role="alert" class="mt-1 text-sm text-red-600">{{ linerError }}</p>
                </div>
                <p v-else class="text-sm text-gray-500">No liners are available.</p>
                <p v-if="saveError" role="alert" class="text-sm text-red-600">{{ saveError }}</p>
                <div class="flex justify-end gap-3 border-t border-blue-50 pt-4">
                    <button type="button" :disabled="saving" class="rounded-lg border border-gray-200 px-4 py-2 text-sm disabled:opacity-50" @click="close">Cancel</button>
                    <button type="submit" :disabled="!canSave" class="rounded-lg bg-[#2196F3] px-4 py-2 text-sm font-semibold text-white hover:bg-[#0D47A1] disabled:opacity-50">
                        {{ saving ? 'Saving...' : 'Save changes' }}
                    </button>
                </div>
            </form>
        </dialog>
    </Teleport>
</template>

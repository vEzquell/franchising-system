<script setup>
import { inject, nextTick, ref, watch } from 'vue'
import { X } from 'lucide-vue-next'
import FranchiseSelect from '../Transactions/FranchiseSelect.vue'
import http from '@/lib/http'
const open = defineModel('open', { type: Boolean, default: false })
const props = defineProps({ owner: { type: Object, default: null } })
const emit = defineEmits(['assigned'])
const route = inject('route')
const dialog = ref(null)
const franchise = ref(null)
const startedAt = ref('')
const expirationDate = ref('')
watch(franchise, value => { expirationDate.value = value?.expiration_date?.slice(0, 10) ?? '' })
const saving = ref(false)
const errors = ref({})
const errorMessage = ref('')
watch(open, async value => {
    await nextTick()
    if (value) {
        franchise.value = null
        expirationDate.value = ''
        const date = new Date()
        startedAt.value = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
        errors.value = {}
        errorMessage.value = ''
        dialog.value.showModal()
    } else dialog.value.close()
})
function close() { if (!saving.value) open.value = false }
async function submit() {
    if (saving.value) return
    if (!franchise.value) { errors.value = { franchise_id: 'Select a franchise from the search results.' }; return }
    saving.value = true
    errors.value = {}
    errorMessage.value = ''
    try {
        await http.post(route('owners.franchises.store', props.owner.id), { franchise_id: franchise.value.id, started_at: startedAt.value, expiration_date: expirationDate.value })
        open.value = false
        emit('assigned')
    } catch (error) {
        if (error.response?.status === 422) errors.value = Object.fromEntries(Object.entries(error.response.data.errors ?? {}).map(([field, messages]) => [field, messages[0]]))
        else errorMessage.value = 'Unable to assign the franchise. Refresh the page and try again.'
    } finally { saving.value = false }
}
</script>
<template>
    <Teleport to="body">
        <dialog ref="dialog" aria-labelledby="assign-franchise-title" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl backdrop:bg-black/50" @cancel.prevent="close" @close="open = false">
            <form class="space-y-4" @submit.prevent="submit">
                <div class="flex items-center justify-between"><h2 id="assign-franchise-title" class="text-lg font-bold text-[#0D47A1]">Assign Franchise</h2><button type="button" aria-label="Close Assign Franchise" :disabled="saving" class="rounded-lg p-2 hover:bg-blue-50" @click="close"><X class="h-5 w-5" /></button></div>
                <p class="text-sm text-gray-600">Owner: <strong>{{ [owner?.first_name, owner?.middle_name, owner?.last_name, owner?.suffix].filter(Boolean).join(' ') }}</strong></p>
                <p v-if="errorMessage" role="alert" class="text-sm text-red-600">{{ errorMessage }}</p>
                <fieldset :disabled="saving" class="space-y-4">
                    <FranchiseSelect v-if="open" v-model="franchise" search-route="owners.franchises" :error="errors.franchise_id" />
                    <div><label for="ownership-start-date" class="mb-2 block text-sm font-medium text-gray-700">Ownership start date</label><input id="ownership-start-date" v-model="startedAt" type="date" required :aria-invalid="!!errors.started_at" aria-describedby="ownership-start-error" class="w-full rounded-lg border border-[#90CAF9] px-3 py-2.5 text-sm" /><p v-if="errors.started_at" id="ownership-start-error" role="alert" class="mt-1 text-sm text-red-600">{{ errors.started_at }}</p></div>
                    <div>
                        <label for="assignment-expiration-date" class="mb-2 block text-sm font-medium text-gray-700">Franchise expiration date</label>
                        <input id="assignment-expiration-date" v-model="expirationDate" type="date" required :min="startedAt" :aria-invalid="!!errors.expiration_date" aria-describedby="assignment-expiration-error" class="w-full rounded-lg border border-[#90CAF9] px-3 py-2.5 text-sm" />
                        <p v-if="errors.expiration_date" id="assignment-expiration-error" role="alert" class="mt-1 text-sm text-red-600">{{ errors.expiration_date }}</p>
                        <p class="mt-1 text-xs text-gray-500">Saved on the franchise and used as the previous expiration date when renewing.</p>
                    </div>
                </fieldset>
                <p class="text-xs text-gray-500">Assign an unassigned franchise here. Existing ownership changes must go through an ownership transfer.</p>
                <div class="flex justify-end gap-3 border-t border-blue-50 pt-4"><button type="button" :disabled="saving" class="rounded-lg border px-4 py-2 text-sm" @click="close">Cancel</button><button type="submit" :disabled="saving || !franchise" class="rounded-lg bg-[#2196F3] px-4 py-2 text-sm font-semibold text-white hover:bg-[#0D47A1] disabled:opacity-50">{{ saving ? 'Assigning...' : 'Assign Franchise' }}</button></div>
            </form>
        </dialog>
    </Teleport>
</template>

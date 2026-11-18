<script setup>
    import { inject, nextTick, onBeforeUnmount, ref, watch } from 'vue'
    import { X } from 'lucide-vue-next'
    import VehicleForm from '../Transactions/VehicleForm.vue'
    import http from '@/lib/http'

    const open = defineModel('open', { type: Boolean, default: false })
    const props = defineProps({ franchise: Object })
    const emit = defineEmits(['assigned'])
    const route = inject('route')
    const dialog = ref(null)
    const vehicle = ref({})
    const vehicles = ref([])
    const startedAt = ref('')
    const saving = ref(false)
    const loading = ref(false)
    const errors = ref({})
    const errorMessage = ref('')
    
    let request
    async function loadVehicles() {
        request?.abort()
        const current = new AbortController()
        request = current
        loading.value = true
        errorMessage.value = ''
        try {
            const { data } = await http.get(route('franchise.vehicles', props.franchise.id), { signal: current.signal })
            if (!current.signal.aborted) vehicles.value = data.vehicles
        } catch (error) { if (!current.signal.aborted) errorMessage.value = 'Unable to load vehicles. Close and try again.' }
        finally { if (request === current) loading.value = false }
    }
    watch(open, async value => {
        await nextTick()
        if (value) {
            vehicle.value = { mode: 'new', id: '', make_type: '', plate_number: '', motor_number: '', chassis_number: '' }
            vehicles.value = []
            const date = new Date()
            startedAt.value = `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`
            errors.value = {}
            dialog.value.showModal()
            loadVehicles()
        } else { request?.abort(); dialog.value.close() }
    })
    function close() { if (!saving.value) open.value = false }
    async function submit() {
        if (saving.value) return
        saving.value = true
        errors.value = {}
        errorMessage.value = ''
        try {
            await http.post(route('franchise.vehicles.store', props.franchise.id), { vehicle: vehicle.value, started_at: startedAt.value })
            open.value = false
            emit('assigned')
        } catch (error) {
            if (error.response?.status === 422) errors.value = Object.fromEntries(Object.entries(error.response.data.errors ?? {}).map(([field, messages]) => [field, messages[0]]))
            else errorMessage.value = 'Unable to assign vehicle. Refresh and try again.'
        } finally { saving.value = false }
    }
    onBeforeUnmount(() => request?.abort())
</script>
<template>
    <Teleport to="body">
        <dialog ref="dialog" aria-labelledby="assign-vehicle-title" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl backdrop:bg-black/50" @cancel.prevent="close" @close="open = false">
            <form class="space-y-4" @submit.prevent="submit">
                <div class="flex items-center justify-between"><h2 id="assign-vehicle-title" class="text-lg font-bold text-[#0D47A1]">Assign Vehicle</h2><button type="button" :disabled="saving" aria-label="Close vehicle assignment" class="rounded-lg p-2 hover:bg-blue-50" @click="close"><X class="h-5 w-5" /></button></div>
                <p class="text-sm">Franchise: <strong>{{ franchise?.franchise_number }}</strong></p>
                <p v-if="loading" role="status" class="text-sm text-gray-500">Loading vehicles...</p>
                <p v-if="errorMessage" role="alert" class="text-sm text-red-600">{{ errorMessage }}</p>
                <fieldset :disabled="saving || loading" class="space-y-4">
                    <VehicleForm v-model="vehicle" :vehicles="vehicles" :errors="errors" :editable="false" />
                    <div><label for="vehicle-started-at" class="mb-2 block text-sm font-medium">Assignment start date</label><input id="vehicle-started-at" v-model="startedAt" type="date" required class="w-full rounded-lg border border-[#90CAF9] px-3 py-2.5 text-sm" /><p v-if="errors.started_at" role="alert" class="text-sm text-red-600">{{ errors.started_at }}</p></div>
                </fieldset>
                <p class="text-xs text-gray-500">For recording the existing vehicle on a franchise. Replacements must use a substitution transaction.</p>
                <div class="flex justify-end gap-3 border-t pt-4"><button type="button" :disabled="saving" class="rounded-lg border px-4 py-2 text-sm" @click="close">Cancel</button><button type="submit" :disabled="saving || loading" class="rounded-lg bg-[#2196F3] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ saving ? 'Saving...' : 'Assign Vehicle' }}</button></div>
            </form>
        </dialog>
    </Teleport>
</template>

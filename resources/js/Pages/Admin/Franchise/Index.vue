<script setup>
import { computed, inject, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { FileText, Search, ChevronLeft, ChevronRight, Pencil, X, Plus, Eye, ArrowLeftRight, Car, Paperclip } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { formatOwnerName as ownerName } from '@/lib/formatOwnerName'
import http from '@/lib/http'
import CreateFranchise from './Create.vue'
import AssignVehicle from './AssignVehicle.vue'
import CurrentOwnerDocuments from './CurrentOwnerDocuments.vue'


const props = defineProps({
    franchises: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '', status: '' }) },
})
const route = inject('route')
const franchises = ref(props.franchises)
const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? '')
const loading = ref(false)
const loadError = ref('')
let searchTimer
let listRequest

const statusStyles = {
    active: 'bg-green-50 text-green-700',
    expired: 'bg-red-50 text-red-700',
    suspended: 'bg-amber-50 text-amber-800',
    unavailable: 'bg-gray-100 text-gray-700',
}
const statusLabel = value => value ? value.charAt(0).toUpperCase() + value.slice(1) : 'Unknown'

const formatDate = value => {
    if (!value) return 'Not set'
    const date = new Date(`${value.slice(0, 10)}T00:00:00`)
    return Number.isNaN(date.getTime()) ? 'Not set' : date.toLocaleDateString()
}

async function loadFranchises(page = 1) {
    clearTimeout(searchTimer)
    listRequest?.abort()
    const request = new AbortController()
    listRequest = request
    loading.value = true
    loadError.value = ''
    try {
        const { data } = await http.get(route('franchise.index'), {
            params: { search: search.value, status: status.value, page },
            signal: request.signal,
        })
        if (request.signal.aborted) return
        if (page > data.franchises.last_page) {
            await loadFranchises(data.franchises.last_page)
            return
        }
        franchises.value = data.franchises
    } catch (error) {
        if (request.signal.aborted) return
        loadError.value = error.response?.status === 403
            ? 'You do not have permission to view franchises.'
            : error.response?.status === 401
                ? 'Your session ended. Sign in again.'
                : 'Unable to load franchises. Please try again.'
    } finally {
        if (listRequest === request) loading.value = false
    }
}
watch(search, () => {
    clearTimeout(searchTimer)
    listRequest?.abort()
    searchTimer = setTimeout(() => loadFranchises(), 300)
})
watch(status, () => loadFranchises())
watch(() => props.franchises, value => { franchises.value = value })
watch(() => props.filters, value => {
    search.value = value.search ?? ''
    status.value = value.status ?? ''
})
onBeforeUnmount(() => { clearTimeout(searchTimer); listRequest?.abort() })
const assignVehicleOpen = ref(false)
const vehicleFranchise = ref(null)
function openVehicleAssignment(franchise) { vehicleFranchise.value = franchise; assignVehicleOpen.value = true }
function vehicleAssigned() {
    notice.value = 'Vehicle assigned.'
    loadFranchises(franchises.value.current_page)
}

const createOpen = ref(false)
function franchiseCreated() {
    notice.value = 'Franchise created.'
    loadFranchises()
}
const editDialog = ref(null)
const selected = ref(null)
const saving = ref(false)
const errors = ref({})
const saveError = ref('')
const notice = ref('')
const editForm = reactive({ franchise_number: '', status: 'active', date_issued: '', expiration_date: '' })
function openEdit(franchise) {
    selected.value = franchise
    Object.assign(editForm, {
        franchise_number: franchise.franchise_number,
        status: franchise.recorded_status ?? franchise.status,
        date_issued: franchise.date_issued?.slice(0, 10) ?? '',
        expiration_date: franchise.expiration_date?.slice(0, 10) ?? '',
    })
    errors.value = {}
    saveError.value = ''
    editDialog.value.showModal()
}
function closeEdit() {
    if (!saving.value) editDialog.value.close()
}
async function saveEdit() {
    if (saving.value) return
    saving.value = true
    errors.value = {}
    saveError.value = ''
    try {
        await http.post(route('franchise.update', selected.value.id), {
            ...editForm,
            franchise_number: editForm.franchise_number.trim(),
            date_issued: editForm.date_issued || null,
            expiration_date: editForm.expiration_date || null,
        })
        editDialog.value.close()
        notice.value = 'Franchise updated.'
        await loadFranchises(franchises.value.current_page)
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = Object.fromEntries(Object.entries(error.response.data.errors ?? {}).map(([field, messages]) => [field, messages[0]]))
        } else {
            saveError.value = error.response?.status === 403 ? 'You do not have permission to edit franchises.' : 'Unable to save. Refresh the page and try again.'
        }
    } finally {
        saving.value = false
    }
}
const viewDialog = ref(null)
const viewed = ref(null)
const page = usePage()
const canViewOwnerships = computed(() => page.props.auth?.isSuperAdmin || page.props.auth?.permissions?.includes('view ownerships'))
function openView(franchise) {
    viewed.value = franchise
    viewDialog.value.showModal()
}
function closeView() {
    viewDialog.value.close()
    viewed.value = null
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Franchises" />
        <div class="min-h-full bg-[#E3F2FD]/30 p-4 md:p-6">
            <p v-if="notice" role="status" class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ notice }}</p>
            <p v-if="loadError" role="alert" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">
                {{ loadError }} <button type="button" class="underline" @click="loadFranchises()">Retry</button>
            </p>
            <p v-if="loading" role="status" class="mb-3 text-sm text-gray-500">Loading franchises...</p>
            <div class="overflow-hidden rounded-xl border border-[#90CAF9]/50 bg-white shadow-sm">
                <div class="flex flex-col gap-4 border-b border-[#E3F2FD] p-5 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <FileText class="h-6 w-6 text-[#0D47A1]" />
                            <h1 class="text-xl font-bold text-[#0D47A1]">Franchises</h1>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">Browse franchise records, current owners, and validity dates.</p>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div class="relative">
                            <Search aria-hidden="true" class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#2196F3]" />
                            <input v-model="search" type="search" aria-label="Search franchise number or current owner" maxlength="255" placeholder="Franchise number or owner..." class="w-full rounded-lg border border-[#90CAF9] py-2.5 pl-10 pr-4 text-sm text-gray-700 outline-none focus:ring-2 focus:ring-[#90CAF9]/50 sm:w-72" />
                        </div>
                        <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#2196F3] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#0D47A1]" @click="createOpen = true"><Plus class="h-4 w-4" /> Add Franchise</button>
                    </div>
                </div>
                <div class="overflow-x-auto" :aria-busy="loading">
                    <table class="w-full min-w-[850px] text-left text-sm">
                        <thead class="bg-[#E3F2FD] text-[#0D47A1]">
                            <tr>
                                <th scope="col" class="px-5 py-4 font-semibold">#</th>
                                <th scope="col" class="px-5 py-4 font-semibold">Franchise number</th>
                                <th scope="col" class="px-5 py-4 font-semibold">Current owner</th>
                                <th scope="col" class="px-5 py-4 font-semibold">Status</th>
                                <th scope="col" class="px-5 py-4 font-semibold">Date issued</th>
                                <th scope="col" class="px-5 py-4 font-semibold">Expiration date</th>
                                <th scope="col" class="px-5 py-4 text-center font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E3F2FD]">
                            <tr v-for="(franchise, index) in franchises.data" :key="franchise.id" class="hover:bg-blue-50/50">
                                <td class="px-5 py-4 text-gray-500">{{ franchises.from + index }}</td>
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-gray-800">{{ franchise.franchise_number }}</p>
                                    <p v-if="franchise.liner" class="mt-1 max-w-[16rem] break-words text-xs text-gray-500">
                                        <span class="sr-only">Liner </span>{{ franchise.liner.name }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 text-gray-700">
                                    <template v-if="franchise.ownerships.length">
                                        <p v-for="ownership in franchise.ownerships" :key="ownership.id">{{ ownerName(ownership.owner) || 'Unknown owner' }}</p>
                                    </template>
                                    <span v-else class="text-gray-500">No current owner</span>
                                </td>
                                <td class="px-5 py-4"><span class="inline-block rounded-full px-3 py-1 text-xs font-semibold" :class="statusStyles[franchise.status] ?? statusStyles.unavailable">{{ statusLabel(franchise.status) }}</span></td>
                                <td class="px-5 py-4 text-gray-500">{{ formatDate(franchise.date_issued) }}</td>
                                <td class="px-5 py-4 text-gray-500">{{ formatDate(franchise.expiration_date) }}</td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center justify-center gap-2">
                                        <button type="button" title="View franchise" :aria-label="`View ${franchise.franchise_number}`" class="rounded-lg bg-slate-100 p-2 text-slate-700 hover:bg-slate-200" @click="openView(franchise)"><Eye aria-hidden="true" class="h-4 w-4" /></button>
                                        <button type="button" title="Edit franchise" :aria-label="`Edit ${franchise.franchise_number}`" class="rounded-lg bg-[#E3F2FD] p-2 text-[#0D47A1] hover:bg-[#90CAF9]" @click="openEdit(franchise)"><Pencil aria-hidden="true" class="h-4 w-4" /></button>
                                        <button type="button" title="Assign vehicle" :aria-label="`Assign vehicle to ${franchise.franchise_number}`" class="rounded-lg bg-teal-50 p-2 text-teal-700 hover:bg-teal-100" @click="openVehicleAssignment(franchise)"><Car aria-hidden="true" class="h-4 w-4" /></button>
                                        <Link :href="route('transactions.index', { create: 1, franchise: franchise.id })" title="Add transaction" :aria-label="`Add transaction for ${franchise.franchise_number}`" class="rounded-lg bg-violet-50 p-2 text-violet-700 hover:bg-violet-100" ><ArrowLeftRight aria-hidden="true" class="h-4 w-4" /></Link>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!franchises.data.length">
                                <td colspan="7" class="px-5 py-16 text-center">
                                    <FileText class="mx-auto mb-3 h-10 w-10 text-[#2196F3]" />
                                    <p class="font-medium text-gray-700">No franchises found</p>
                                    <p class="mt-1 text-gray-500">{{ search || status ? 'Try another search.' : 'Franchise records will appear here once created.' }}</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-col gap-3 border-t border-[#E3F2FD] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-gray-500">Showing {{ franchises.from ?? 0 }} to {{ franchises.to ?? 0 }} of {{ franchises.total }} franchises</p>
                    <nav aria-label="Franchise pagination" class="flex items-center gap-3 text-sm text-[#0D47A1]">
                        <button type="button" :disabled="loading || !franchises.prev_page_url" aria-label="Previous page" class="rounded-lg border border-[#90CAF9] p-2 hover:bg-[#E3F2FD] disabled:opacity-40" @click="loadFranchises(franchises.current_page - 1)"><ChevronLeft class="h-4 w-4" /></button>
                        <span>Page {{ franchises.current_page }} of {{ franchises.last_page }}</span>
                        <button type="button" :disabled="loading || !franchises.next_page_url" aria-label="Next page" class="rounded-lg border border-[#90CAF9] p-2 hover:bg-[#E3F2FD] disabled:opacity-40" @click="loadFranchises(franchises.current_page + 1)"><ChevronRight class="h-4 w-4" /></button>
                    </nav>
                </div>
            </div>
        </div>
        <dialog ref="editDialog" aria-labelledby="edit-franchise-title" class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-md overflow-y-auto rounded-xl border border-blue-100 bg-white p-6 shadow-xl backdrop:bg-black/50" @cancel.prevent="closeEdit">
            <form class="space-y-4" @submit.prevent="saveEdit">
                <div class="flex items-center justify-between">
                    <h2 id="edit-franchise-title" class="text-lg font-bold text-[#0D47A1]">Edit Franchise</h2>
                    <button type="button" aria-label="Close edit dialog" :disabled="saving" class="rounded-lg p-2 hover:bg-blue-50" @click="closeEdit"><X class="h-5 w-5" /></button>
                </div>
                <p v-if="saveError" role="alert" class="text-sm text-red-600">{{ saveError }}</p>
                <div>
                    <label for="edit-franchise-number" class="mb-2 block text-sm font-medium text-gray-700">Franchise number</label>
                    <input id="edit-franchise-number" v-model="editForm.franchise_number" required maxlength="50" :disabled="saving" :aria-invalid="!!errors.franchise_number" class="w-full rounded-lg border border-[#90CAF9] px-3 py-2 text-sm" />
                    <p v-if="errors.franchise_number" role="alert" class="mt-1 text-sm text-red-600">{{ errors.franchise_number }}</p>
                </div>
                <div>
                    <label for="edit-franchise-status" class="mb-2 block text-sm font-medium text-gray-700">Status</label>
                    <select id="edit-franchise-status" v-model="editForm.status" :disabled="saving" aria-describedby="edit-franchise-status-help" class="w-full rounded-lg border border-[#90CAF9] px-3 py-2 text-sm">
                        <option v-for="value in ['active', 'expired', 'suspended', 'unavailable']" :key="value" :value="value">{{ statusLabel(value) }}</option>
                    </select>
                    <p id="edit-franchise-status-help" class="mt-1 text-xs text-gray-500">Active franchises show as Expired after their expiration date.</p>
                    <p v-if="errors.status" role="alert" class="mt-1 text-sm text-red-600">{{ errors.status }}</p>
                </div>
                <div v-for="field in ['date_issued', 'expiration_date']" :key="field">
                    <label :for="`edit-${field}`" class="mb-2 block text-sm font-medium text-gray-700">{{ field === 'date_issued' ? 'Date issued' : 'Expiration date' }}</label>
                    <input :id="`edit-${field}`" v-model="editForm[field]" type="date" :disabled="saving" :aria-invalid="!!errors[field]" :min="field === 'expiration_date' ? editForm.date_issued : undefined" class="w-full rounded-lg border border-[#90CAF9] px-3 py-2 text-sm" />
                    <p v-if="errors[field]" role="alert" class="mt-1 text-sm text-red-600">{{ errors[field] }}</p>
                </div>
                <div class="flex justify-end gap-3 border-t border-blue-50 pt-4">
                    <button type="button" :disabled="saving" class="rounded-lg border border-gray-200 px-4 py-2 text-sm disabled:opacity-50" @click="closeEdit">Cancel</button>
                    <button type="submit" :disabled="saving" class="rounded-lg bg-[#2196F3] px-4 py-2 text-sm font-semibold text-white hover:bg-[#0D47A1] disabled:opacity-50">{{ saving ? 'Saving...' : 'Save changes' }}</button>
                </div>
            </form>
        </dialog>
        <dialog ref="viewDialog" aria-labelledby="view-franchise-title" class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-2xl overflow-y-auto rounded-xl bg-white p-4 shadow-xl backdrop:bg-black/50 sm:p-6" @cancel.prevent="closeView">
            <div class="mb-5 flex items-center justify-between gap-4">
                <h2 id="view-franchise-title" class="text-lg font-bold text-[#0D47A1]">Franchise details</h2>
                <button type="button" aria-label="Close franchise details" class="rounded-lg p-2 hover:bg-blue-50" @click="closeView"><X class="h-5 w-5" /></button>
            </div>
            <dl v-if="viewed" class="grid gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm sm:grid-cols-2">
                <div><dt class="text-gray-500">Franchise number</dt><dd class="mt-1 font-semibold text-gray-800">{{ viewed.franchise_number }}</dd></div>
                <div v-if="!canViewOwnerships"><dt class="text-gray-500">Current owner</dt><dd class="mt-1 text-gray-800">{{ viewed.ownerships.map(ownership => ownerName(ownership.owner)).filter(Boolean).join(', ') || 'No current owner' }}</dd></div>
                <div><dt class="text-gray-500">Current vehicle</dt><dd class="mt-1"><p v-for="assignment in (viewed.vehicle_assignments ?? [])" :key="assignment.id">{{ assignment.vehicle?.make_type }} - {{ assignment.vehicle?.plate_number || assignment.vehicle?.motor_number || assignment.vehicle?.chassis_number }}</p><span v-if="!viewed.vehicle_assignments?.length">No vehicle assigned</span></dd></div>
                <div>
                    <dt class="text-gray-500">Liner</dt>
                    <dd class="mt-1 break-words text-gray-800">
                        <template v-if="viewed.liner">
                            <p class="font-medium">{{ viewed.liner.name }}</p>
                            <p class="text-gray-600">{{ viewed.liner.route }}</p>
                        </template>
                        <span v-else>No liner linked</span>
                    </dd>
                </div>
                <div><dt class="text-gray-500">Status</dt><dd class="mt-1 text-gray-800">{{ statusLabel(viewed.status) }}</dd></div>
                <div><dt class="text-gray-500">Date issued</dt><dd class="mt-1 text-gray-800">{{ formatDate(viewed.date_issued) }}</dd></div>
                <div><dt class="text-gray-500">Expiration date</dt><dd class="mt-1 text-gray-800">{{ formatDate(viewed.expiration_date) }}</dd></div>
            </dl>
            <section v-if="viewed && canViewOwnerships" aria-labelledby="current-owner-documents-title" class="mt-5 overflow-hidden rounded-xl border border-blue-100">
                <h3 id="current-owner-documents-title" class="flex items-center gap-2 border-b border-blue-100 bg-[#E3F2FD] px-4 py-3 text-sm font-semibold text-[#0D47A1]">
                    <Paperclip aria-hidden="true" class="h-4 w-4" /> Current owner attachments
                </h3>
                <div v-if="viewed.ownerships.length" class="divide-y divide-blue-100">
                    <CurrentOwnerDocuments v-for="ownership in viewed.ownerships" :key="ownership.id" :ownership="ownership" />
                </div>
                <p v-else class="p-4 text-sm text-slate-600">No current owner assigned.</p>
            </section>
        </dialog>
        <AssignVehicle v-model:open="assignVehicleOpen" :franchise="vehicleFranchise" @assigned="vehicleAssigned" />
        <CreateFranchise v-model:open="createOpen" @created="franchiseCreated" />
    </AuthenticatedLayout>
</template>

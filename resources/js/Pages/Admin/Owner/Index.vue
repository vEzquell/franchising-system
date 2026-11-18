<script setup>
    import { inject, onBeforeUnmount, ref, watch } from 'vue'
    import { Head } from '@inertiajs/vue3'
    import { Users, Search, Plus, Eye, Pencil, Link2, X, ChevronLeft, ChevronRight } from 'lucide-vue-next'
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
    import OwnerForm from './Create.vue'
    import AssignFranchise from './AssignFranchise.vue'
    import http from '@/lib/http'

    const props = defineProps({ owners: { type: Object, required: true }, filters: { type: Object, default: () => ({ search: '' }) } })
    const route = inject('route')
    const owners = ref(props.owners)
    const search = ref(props.filters.search ?? '')
    const loading = ref(false)
    const loadError = ref('')
    const notice = ref('')
    const formOpen = ref(false)
    const selected = ref(null)
    const assignOpen = ref(false)
    const assignOwner = ref(null)
    function openAssign(owner) { assignOwner.value = owner; assignOpen.value = true }
    function assigned() { notice.value = 'Franchise assigned. This owner is now available for its renewal.' }
    const viewed = ref(null)
    const viewDialog = ref(null)
    let searchTimer
    let listRequest

const fullName = owner => [
    owner.first_name,
    owner.middle_name ? `${owner.middle_name}.` : null,
    owner.last_name,
    owner.suffix
].filter(Boolean).join(' ')

function openForm(owner = null) {
    selected.value = owner
    formOpen.value = true
}
function openView(owner) {
    viewed.value = owner
    viewDialog.value.showModal()
}
function saved(message) {
    notice.value = message
    loadOwners()
}

async function loadOwners(page = 1) {
    clearTimeout(searchTimer)
    listRequest?.abort()
    const request = new AbortController()
    listRequest = request
    loading.value = true
    loadError.value = ''
    try {
        const { data } = await http.get(route('owners.index'), { params: { search: search.value, page }, signal: request.signal })
        if (request.signal.aborted) return
        if (page > data.owners.last_page) return await loadOwners(data.owners.last_page)
        owners.value = data.owners
    } catch (error) {
        if (!request.signal.aborted) loadError.value = error.response?.status === 403 ? 'You do not have permission to view owners.' : 'Unable to load owners. Refresh the page or try again.'
    } finally {
        if (listRequest === request) loading.value = false
    }
}
watch(search, () => {
    clearTimeout(searchTimer)
    listRequest?.abort()
    searchTimer = setTimeout(() => loadOwners(), 300)
})
watch(() => props.owners, value => { owners.value = value })
watch(() => props.filters.search, value => { search.value = value ?? '' })
onBeforeUnmount(() => { clearTimeout(searchTimer); listRequest?.abort() })
</script>
<template>
    <Head title="Owners" />
    <AuthenticatedLayout>
        <div class="min-h-full bg-[#E3F2FD]/30 p-4 md:p-6">
            <p v-if="notice" role="status" class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ notice }}</p>
            <p v-if="loadError" role="alert" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ loadError }} <button type="button" class="underline" @click="loadOwners()">Retry</button></p>
            <p v-if="loading" role="status" class="mb-3 text-sm text-gray-500">Loading owners...</p>
            <div class="overflow-hidden rounded-xl border border-[#90CAF9]/50 bg-white shadow-sm">
                <div class="flex flex-col gap-4 border-b border-[#E3F2FD] p-5 xl:flex-row xl:items-center xl:justify-between">
                    <div><div class="flex items-center gap-2"><Users class="h-6 w-6 text-[#0D47A1]" /><h1 class="text-xl font-bold text-[#0D47A1]">Owners</h1></div><p class="mt-1 text-sm text-gray-500">Manage owner names, contact numbers, and addresses.</p></div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div class="relative"><Search aria-hidden="true" class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#2196F3]" /><input v-model="search" type="search" aria-label="Search owners" maxlength="255" placeholder="Name, address or contact..." class="w-full rounded-lg border border-[#90CAF9] py-2.5 pl-10 pr-4 text-sm focus:ring-2 focus:ring-[#90CAF9]/50 sm:w-72" /></div>
                        <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#2196F3] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#0D47A1]" @click="openForm()"><Plus class="h-4 w-4" /> Add Owner</button>
                    </div>
                </div>
                <div class="overflow-x-auto" :aria-busy="loading">
                    <table class="w-full min-w-[850px] text-left text-sm">
                        <thead class="bg-[#E3F2FD] text-[#0D47A1]"><tr><th scope="col" class="px-5 py-4">#</th><th scope="col" class="px-5 py-4">Owner</th><th scope="col" class="px-5 py-4">Contact number</th><th scope="col" class="px-5 py-4">Address</th><th scope="col" class="px-5 py-4 text-center">Actions</th></tr></thead>
                        <tbody class="divide-y divide-[#E3F2FD]">
                            <tr v-for="(owner, index) in owners.data" :key="owner.id" class="hover:bg-blue-50/50">
                                <td class="px-5 py-4 text-gray-500">{{ owners.from + index }}</td><td class="px-5 py-4 font-semibold text-gray-800">{{ fullName(owner) }}</td><td class="px-5 py-4 text-gray-600">{{ owner.contact_number || 'Not set' }}</td><td class="max-w-xs px-5 py-4 text-gray-600 break-words">{{ owner.address || 'Not set' }}</td>
                                <td class="px-5 py-4"><div class="flex justify-center gap-2"><button type="button" title="View owner" :aria-label="`View ${fullName(owner)}`" class="rounded-lg bg-slate-100 p-2 text-slate-700 hover:bg-slate-200" @click="openView(owner)"><Eye aria-hidden="true" class="h-4 w-4" /></button><button type="button" title="Edit owner" :aria-label="`Edit ${fullName(owner)}`" class="rounded-lg bg-[#E3F2FD] p-2 text-[#0D47A1] hover:bg-[#90CAF9]" @click="openForm(owner)"><Pencil aria-hidden="true" class="h-4 w-4" /></button><button type="button" title="Assign franchise" :aria-label="`Assign franchise to ${fullName(owner)}`" class="rounded-lg bg-violet-50 p-2 text-violet-700 hover:bg-violet-100" @click="openAssign(owner)"><Link2 aria-hidden="true" class="h-4 w-4" /></button></div></td>
                            </tr>
                            <tr v-if="!owners.data.length"><td colspan="5" class="px-5 py-16 text-center"><Users class="mx-auto mb-3 h-10 w-10 text-[#2196F3]" /><p class="font-medium text-gray-700">No owners found</p><p class="mt-1 text-gray-500">{{ search ? 'Try another search.' : 'Add an owner to get started.' }}</p></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-col gap-3 border-t border-[#E3F2FD] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-gray-500">Showing {{ owners.from ?? 0 }} to {{ owners.to ?? 0 }} of {{ owners.total }} owners</p>
                    <nav aria-label="Owner pagination" class="flex items-center gap-3 text-sm text-[#0D47A1]"><button type="button" :disabled="loading || !owners.prev_page_url" aria-label="Previous page" class="rounded-lg border border-[#90CAF9] p-2 disabled:opacity-40" @click="loadOwners(owners.current_page - 1)"><ChevronLeft class="h-4 w-4" /></button><span>Page {{ owners.current_page }} of {{ owners.last_page }}</span><button type="button" :disabled="loading || !owners.next_page_url" aria-label="Next page" class="rounded-lg border border-[#90CAF9] p-2 disabled:opacity-40" @click="loadOwners(owners.current_page + 1)"><ChevronRight class="h-4 w-4" /></button></nav>
                </div>
            </div>
        </div>
        <AssignFranchise v-model:open="assignOpen" :owner="assignOwner" @assigned="assigned" />
        <OwnerForm v-model:open="formOpen" :owner="selected" @saved="saved" />
        <dialog ref="viewDialog" aria-labelledby="owner-view-title" class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-md overflow-y-auto rounded-xl bg-white p-6 shadow-xl backdrop:bg-black/50">
            <div class="mb-5 flex items-center justify-between"><h2 id="owner-view-title" class="text-lg font-bold text-[#0D47A1]">Owner details</h2><button type="button" aria-label="Close owner details" class="rounded-lg p-2 hover:bg-blue-50" @click="viewDialog.close()"><X class="h-5 w-5" /></button></div>
            <dl v-if="viewed" class="space-y-4 text-sm"><div><dt class="text-gray-500">Name</dt><dd class="mt-1 font-semibold">{{ fullName(viewed) }}</dd></div><div><dt class="text-gray-500">Contact number</dt><dd class="mt-1">{{ viewed.contact_number || 'Not set' }}</dd></div><div><dt class="text-gray-500">Address</dt><dd class="mt-1 break-words">{{ viewed.address || 'Not set' }}</dd></div></dl>
        </dialog>
    </AuthenticatedLayout>
</template>

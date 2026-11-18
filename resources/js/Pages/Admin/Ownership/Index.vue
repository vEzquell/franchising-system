<script setup>
    import { inject, onBeforeUnmount, ref, watch } from 'vue'
    import { Head } from '@inertiajs/vue3'
    import { Eye, FileText, Search, Paperclip, ChevronLeft, ChevronRight } from 'lucide-vue-next'
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
    import Documents from './Documents.vue'
    import http from '@/lib/http'
    const props = defineProps({ ownerships: { type: Object, required: true }, filters: { type: Object, default: () => ({ search: '', period: '' }) }, today: { type: String, required: true } })
    const route = inject('route')
    const ownerships = ref(props.ownerships)
    const search = ref(props.filters.search ?? '')
    const period = ref(props.filters.period ?? '')
    const loading = ref(false)
    const errorMessage = ref('')
    const notice = ref('')
    const documentsOpen = ref(false)
    const documentsMode = ref('view')
    const selectedId = ref(null)
    let request
    let timer

    const fullName = owner => owner
        ? [
            owner.first_name,
            owner.middle_name ? `${owner.middle_name.replace(/\.$/, '')}.` : null,
            owner.last_name,
            owner.suffix
        ].filter(Boolean).join(' ')
        : 'Unknown owner'
    const date = value => value?.slice(0, 10) ?? ''

    const state = ownership => date(ownership.started_at) > props.today ? 'Upcoming' : ownership.ended_at && date(ownership.ended_at) <= props.today ? 'Previous' : 'Current'
    function openDocuments(ownership, mode) {
        selectedId.value = ownership.id
        documentsMode.value = mode
        documentsOpen.value = true
    }
    function uploaded() { notice.value = 'Ownership documents uploaded.'; load(ownerships.value.current_page) }
    async function load(page = 1) {
        clearTimeout(timer)
        request?.abort()
        const current = new AbortController()
        request = current
        loading.value = true
        errorMessage.value = ''
        try {
            const { data } = await http.get(route('ownerships.index'), { params: { search: search.value, period: period.value, page }, signal: current.signal })
            if (current.signal.aborted) return
            if (page > data.ownerships.last_page) return await load(data.ownerships.last_page)
            ownerships.value = data.ownerships
        } catch (error) { if (!current.signal.aborted) errorMessage.value = 'Unable to load ownership records. Refresh the page or try again.' }
        finally { if (request === current) loading.value = false }
    }
    watch(search, () => { clearTimeout(timer); request?.abort(); timer = setTimeout(() => load(), 300) })
    watch(period, () => load())
    watch(() => props.ownerships, value => { ownerships.value = value })
    watch(() => props.filters, value => { search.value = value.search ?? ''; period.value = value.period ?? '' })
    onBeforeUnmount(() => { clearTimeout(timer); request?.abort() })
</script>
<template>
    <Head title="Ownership" />
    <AuthenticatedLayout>
        <div class="min-h-full bg-[#E3F2FD]/30 p-4 md:p-6">
            <p v-if="notice" role="status" class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ notice }}</p>
            <p v-if="errorMessage" role="alert" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ errorMessage }} <button type="button" class="underline" @click="load()">Retry</button></p>
            <p v-if="loading" role="status" class="mb-3 text-sm text-gray-500">Loading ownership records...</p>
            <div class="overflow-hidden rounded-xl border border-[#90CAF9]/50 bg-white shadow-sm">
                <div class="flex flex-col gap-4 border-b border-[#E3F2FD] p-5 xl:flex-row xl:items-center xl:justify-between">
                    <div><div class="flex items-center gap-2"><FileText class="h-6 w-6 text-[#0D47A1]" /><h1 class="text-xl font-bold text-[#0D47A1]">Ownership</h1></div><p class="mt-1 text-sm text-gray-500">Ownership history and franchise documents, including previous owners.</p></div>
                    <div class="flex flex-col gap-3 sm:flex-row"><div class="relative"><Search aria-hidden="true" class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#2196F3]" /><input v-model="search" type="search" aria-label="Search ownership by franchise number or owner" maxlength="255" placeholder="Franchise number or owner..." class="w-full rounded-lg border border-[#90CAF9] py-2.5 pl-10 pr-4 text-sm sm:w-72" /></div><select v-model="period" aria-label="Filter ownership period" class="rounded-lg border border-[#90CAF9] bg-white px-3 py-2.5 text-sm"><option value="">All ownerships</option><option value="current">Current</option><option value="previous">Previous</option><option value="upcoming">Upcoming</option></select></div>
                </div>
                <div class="overflow-x-auto" :aria-busy="loading">
                    <table class="w-full min-w-[850px] text-left text-sm"><thead class="bg-[#E3F2FD] text-[#0D47A1]"><tr><th scope="col" class="px-5 py-4">#</th><th scope="col" class="px-5 py-4">Franchise number</th><th scope="col" class="px-5 py-4">Owner</th><th scope="col" class="px-5 py-4">Started</th><th scope="col" class="px-5 py-4">Ended</th><th scope="col" class="px-5 py-4">Period</th><th scope="col" class="px-5 py-4">Documents</th><th scope="col" class="px-5 py-4">Actions</th></tr></thead>
                        <tbody class="divide-y divide-[#E3F2FD]">
                            <tr v-for="(ownership, index) in ownerships.data" :key="ownership.id" class="hover:bg-blue-50/50">
                                <td class="px-5 py-4 text-gray-500">{{ ownerships.from + index }}</td>
                                <td class="px-5 py-4 font-semibold">{{ ownership.franchise?.franchise_number }}</td>
                                <td class="px-5 py-4">{{ fullName(ownership.owner) }}</td>
                                <td class="px-5 py-4 text-gray-500">{{ date(ownership.started_at) }}</td>
                                <td class="px-5 py-4 text-gray-500">{{ date(ownership.ended_at) || 'Present' }}</td>
                                <td class="px-5 py-4"><span class="rounded-full px-3 py-1 text-xs font-semibold" :class="state(ownership) === 'Current' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'">{{ state(ownership) }}</span></td>
                                <td class="px-5 py-4">{{ ownership.attachments_count }}</td>
                                <td class="px-5 py-4">
                                    <div class="flex gap-2">
                                        <button type="button" title="View attachments" :aria-label="`View attachments for ${fullName(ownership.owner)}`" class="inline-flex items-center rounded-lg bg-blue-50 p-2 text-[#0D47A1] hover:bg-blue-100" @click="openDocuments(ownership, 'view')"><Eye aria-hidden="true" class="h-4 w-4" /></button>
                                        <button type="button" title="Add attachments" :aria-label="`Add attachments for ${fullName(ownership.owner)}`" class="inline-flex items-center rounded-lg border border-blue-100 p-2 text-[#0D47A1] hover:bg-blue-50" @click="openDocuments(ownership, 'add')"><Paperclip aria-hidden="true" class="h-4 w-4" /></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!ownerships.data.length"><td colspan="8" class="px-5 py-16 text-center text-gray-500">{{ search || period ? 'No matching ownership records.' : 'No ownership records yet. Assign a franchise to an owner on the Owners page.' }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-col gap-3 border-t border-[#E3F2FD] px-5 py-4 sm:flex-row sm:items-center sm:justify-between"><p class="text-sm text-gray-500">Showing {{ ownerships.from ?? 0 }} to {{ ownerships.to ?? 0 }} of {{ ownerships.total }} records</p><nav aria-label="Ownership pagination" class="flex items-center gap-3 text-sm text-[#0D47A1]"><button type="button" :disabled="loading || !ownerships.prev_page_url" aria-label="Previous page" class="rounded-lg border p-2 disabled:opacity-40" @click="load(ownerships.current_page - 1)"><ChevronLeft class="h-4 w-4" /></button><span>Page {{ ownerships.current_page }} of {{ ownerships.last_page }}</span><button type="button" :disabled="loading || !ownerships.next_page_url" aria-label="Next page" class="rounded-lg border p-2 disabled:opacity-40" @click="load(ownerships.current_page + 1)"><ChevronRight class="h-4 w-4" /></button></nav></div>
            </div>
        </div>
        <Documents v-model:open="documentsOpen" :ownership-id="selectedId" :mode="documentsMode" @uploaded="uploaded" />
    </AuthenticatedLayout>
</template>

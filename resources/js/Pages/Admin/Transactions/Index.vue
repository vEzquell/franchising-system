<script setup>
import { inject, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import { FileText, Search, Plus, Eye, ChevronLeft, ChevronRight } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import CreateTransaction from './Create.vue'
import ShowTransaction from './Show.vue'
import http from '@/lib/http'
const props = defineProps({ transactions: { type: Object, required: true }, filters: { type: Object, default: () => ({ search: '', status: '' }) } })
const route = inject('route')
const transactions = ref(props.transactions)
const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? '')
const loading = ref(false)
const errorMessage = ref('')
const notice = ref('')
const createOpen = ref(false)
const showOpen = ref(false)
const selectedId = ref(null)
function show(transaction) { selectedId.value = transaction.id; showOpen.value = true }
const initialFranchise = ref(null)
const statuses = ['pending', 'under_review', 'approved', 'rejected', 'cancelled']
const label = value => value ? value.replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase()) : 'Not set'
const formatTime = value => value ? new Date(value).toLocaleString() : 'Not set'
let request
let searchTimer
let shortcutRequest
async function loadTransactions(page = 1) {
    clearTimeout(searchTimer)
    request?.abort()
    const current = new AbortController()
    request = current
    loading.value = true
    errorMessage.value = ''
    try {
        const { data } = await http.get(route('transactions.index'), { params: { search: search.value, status: status.value, page }, signal: current.signal })
        if (current.signal.aborted) return
        if (page > data.transactions.last_page) return await loadTransactions(data.transactions.last_page)
        transactions.value = data.transactions
    } catch (error) {
        if (!current.signal.aborted) errorMessage.value = 'Unable to load transactions. Refresh the page or try again.'
    } finally { if (request === current) loading.value = false }
}
function openCreate() { initialFranchise.value = null; createOpen.value = true }
function created() { notice.value = 'Transaction submitted as pending.'; loadTransactions() }
function statusUpdated() { notice.value = 'Transaction status updated.'; loadTransactions(transactions.value.current_page) }
watch(search, () => { clearTimeout(searchTimer); request?.abort(); searchTimer = setTimeout(() => loadTransactions(), 300) })
watch(status, () => loadTransactions())
watch(() => props.transactions, value => { transactions.value = value })
watch(() => props.filters, value => { search.value = value.search ?? ''; status.value = value.status ?? '' })
onMounted(async () => {
    const params = new URLSearchParams(window.location.search)
    if (params.get('create') !== '1') return
    if (params.get('franchise')) {
        shortcutRequest = new AbortController()
        try {
            const { data } = await http.get(route('transactions.franchises'), { params: { id: params.get('franchise') }, signal: shortcutRequest.signal })
            if (shortcutRequest.signal.aborted) return
            initialFranchise.value = data.franchises[0] ?? null
        } catch (error) {
            if (shortcutRequest.signal.aborted) return
            errorMessage.value = 'Unable to preselect the franchise. Search for it in the transaction form.'
        }
    }
    createOpen.value = true
})
onBeforeUnmount(() => { clearTimeout(searchTimer); request?.abort(); shortcutRequest?.abort() })
</script>
<template>
    <Head title="Transactions" />
    <AuthenticatedLayout>
        <div class="min-h-full bg-[#E3F2FD]/30 p-4 md:p-6">
            <p v-if="notice" role="status" class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ notice }}</p>
            <p v-if="errorMessage" role="alert" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ errorMessage }} <button type="button" class="underline" @click="loadTransactions()">Retry</button></p>
            <p v-if="loading" role="status" class="mb-3 text-sm text-gray-500">Loading transactions...</p>
            <div class="overflow-hidden rounded-xl border border-[#90CAF9]/50 bg-white shadow-sm">
                <div class="flex flex-col gap-4 border-b border-[#E3F2FD] p-5 xl:flex-row xl:items-center xl:justify-between">
                    <div><div class="flex items-center gap-2"><FileText class="h-6 w-6 text-[#0D47A1]" /><h1 class="text-xl font-bold text-[#0D47A1]">Transactions</h1></div><p class="mt-1 text-sm text-gray-500">Create and track franchise transaction requests.</p></div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div class="relative"><Search aria-hidden="true" class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#2196F3]" /><input v-model="search" aria-label="Search transactions by franchise number" type="search" maxlength="255" placeholder="Search franchise number..." class="w-full rounded-lg border border-[#90CAF9] py-2.5 pl-10 pr-4 text-sm sm:w-64" /></div>
                        <select v-model="status" aria-label="Filter transactions by status" class="rounded-lg border border-[#90CAF9] bg-white px-3 py-2.5 text-sm"><option value="">All statuses</option><option v-for="value in statuses" :key="value" :value="value">{{ label(value) }}</option></select>
                        <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#2196F3] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#0D47A1]" @click="openCreate"><Plus class="h-4 w-4" /> New Transaction</button>
                    </div>
                </div>
                <div class="overflow-x-auto" :aria-busy="loading">
                    <table class="w-full min-w-[700px] text-left text-sm">
                        <thead class="bg-[#E3F2FD] text-[#0D47A1]"><tr><th scope="col" class="px-5 py-4">#</th><th scope="col" class="px-5 py-4">Franchise number</th><th scope="col" class="px-5 py-4">Type</th><th scope="col" class="px-5 py-4">Status</th><th scope="col" class="px-5 py-4">Created</th><th scope="col" class="px-5 py-4">Actions</th></tr></thead>
                        <tbody class="divide-y divide-[#E3F2FD]">
                            <tr v-for="(transaction, index) in transactions.data" :key="transaction.id" class="hover:bg-blue-50/50"><td class="px-5 py-4 text-gray-500">{{ transactions.from + index }}</td><td class="px-5 py-4 font-semibold text-gray-800">{{ transaction.franchise?.franchise_number || 'Not assigned' }}</td><td class="px-5 py-4">{{ label(transaction.transaction_type) }}</td><td class="px-5 py-4"><span class="rounded-full px-3 py-1 text-xs font-semibold" :class="transaction.status === 'approved' ? 'bg-green-50 text-green-700' : transaction.status === 'rejected' ? 'bg-red-50 text-red-700' : 'bg-blue-50 text-blue-800'">{{ label(transaction.status) }}</span></td><td class="px-5 py-4 text-gray-500">{{ formatTime(transaction.created_at) }}</td><td class="px-5 py-4"><button type="button" title="View transaction and attachments" :aria-label="`View transaction ${transaction.id}`" class="rounded-lg bg-blue-50 p-2 text-[#0D47A1]" @click="show(transaction)"><Eye class="h-4 w-4" /></button></td></tr>
                            <tr v-if="!transactions.data.length"><td colspan="6" class="px-5 py-16 text-center"><FileText class="mx-auto mb-3 h-10 w-10 text-[#2196F3]" /><p class="font-medium text-gray-700">No transactions found</p><p class="mt-1 text-gray-500">{{ search || status ? 'Try another search or filter.' : 'Start with New Transaction.' }}</p></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-col gap-3 border-t border-[#E3F2FD] px-5 py-4 sm:flex-row sm:items-center sm:justify-between"><p class="text-sm text-gray-500">Showing {{ transactions.from ?? 0 }} to {{ transactions.to ?? 0 }} of {{ transactions.total }} transactions</p><nav aria-label="Transaction pagination" class="flex items-center gap-3 text-sm text-[#0D47A1]"><button type="button" :disabled="loading || !transactions.prev_page_url" aria-label="Previous page" class="rounded-lg border p-2 disabled:opacity-40" @click="loadTransactions(transactions.current_page - 1)"><ChevronLeft class="h-4 w-4" /></button><span>Page {{ transactions.current_page }} of {{ transactions.last_page }}</span><button type="button" :disabled="loading || !transactions.next_page_url" aria-label="Next page" class="rounded-lg border p-2 disabled:opacity-40" @click="loadTransactions(transactions.current_page + 1)"><ChevronRight class="h-4 w-4" /></button></nav></div>
            </div>
        </div>
        <ShowTransaction v-model:open="showOpen" :transaction-id="selectedId" @status-updated="statusUpdated" />
        <CreateTransaction v-model:open="createOpen" :initial-franchise="initialFranchise" @created="created" />
    </AuthenticatedLayout>
</template>

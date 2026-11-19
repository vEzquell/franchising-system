<script setup>
import { inject, onBeforeUnmount, ref, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import { ChevronLeft, ChevronRight, Pencil, Plus, Route, Search } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import http from '@/lib/http'
import LinerForm from './Create.vue'

const props = defineProps({
    liners: { type: Object, required: true },
    filters: { type: Object, required: true },
})

const route = inject('route')
const liners = ref(props.liners)
const search = ref(props.filters.search)
const loading = ref(false)
const loadError = ref('')
const notice = ref('')
const formOpen = ref(false)
const selectedLiner = ref(null)
let searchTimer
let listRequest

function openForm(liner = null) {
    selectedLiner.value = liner
    formOpen.value = true
}

function saved(message) {
    notice.value = message
    loadLiners()
}

async function loadLiners(page = 1) {
    clearTimeout(searchTimer)
    listRequest?.abort()
    const request = new AbortController()
    listRequest = request
    loading.value = true
    loadError.value = ''

    try {
        const { data } = await http.get(route('liners.index'), {
            params: { search: search.value, page },
            signal: request.signal,
        })

        if (!request.signal.aborted) {
            liners.value = data.liners
        }
    } catch (error) {
        if (!request.signal.aborted) {
            loadError.value = error.response?.status === 403
                ? 'You do not have permission to view liners.'
                : 'Unable to load liners.'
        }
    } finally {
        if (listRequest === request && !request.signal.aborted) {
            loading.value = false
        }
    }
}

watch(search, () => {
    clearTimeout(searchTimer)
    listRequest?.abort()
    loading.value = true
    searchTimer = setTimeout(loadLiners, 300)
})

watch(() => props.liners, value => { liners.value = value })
watch(() => props.filters.search, value => { search.value = value })

onBeforeUnmount(() => {
    clearTimeout(searchTimer)
    listRequest?.abort()
})
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Liners" />

        <div class="min-h-full bg-[#E3F2FD]/30 p-4 md:p-6">
            <p v-if="notice" role="status" class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-800">
                {{ notice }}
            </p>
            <p v-if="loadError" role="alert" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">
                {{ loadError }}
                <button type="button" class="underline" @click="loadLiners()">Retry</button>
            </p>
            <p v-if="loading" role="status" class="sr-only">Loading liners...</p>

            <div class="overflow-hidden rounded-xl border border-[#90CAF9]/50 bg-white shadow-sm">
                <div class="flex flex-col gap-4 border-b border-[#E3F2FD] p-5 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-center gap-2">
                        <Route aria-hidden="true" class="h-6 w-6 text-[#0D47A1]" />
                        <h1 class="text-xl font-bold text-[#0D47A1]">Liners</h1>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div class="relative">
                            <Search aria-hidden="true" class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#2196F3]" />
                            <input v-model="search" type="search" aria-label="Search liners" maxlength="191"
                                placeholder="Search name or route..."
                                class="w-full rounded-lg border border-[#90CAF9] py-2.5 pl-10 pr-4 text-sm focus:ring-2 focus:ring-[#90CAF9]/50 sm:w-64" />
                        </div>
                        <button type="button" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-[#2196F3] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#0D47A1]" @click="openForm()">
                            <Plus aria-hidden="true" class="h-4 w-4" /> Add Liner
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto" :aria-busy="loading">
                    <table class="w-full min-w-[460px] text-left text-sm">
                        <thead class="bg-[#E3F2FD] text-[#0D47A1]">
                            <tr>
                                <th scope="col" class="w-16 px-5 py-4">#</th>
                                <th scope="col" class="px-5 py-4">Liner name</th>
                                <th scope="col" class="px-5 py-4">Route</th>
                                <th scope="col" class="w-24 px-5 py-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E3F2FD]">
                            <tr v-for="(liner, index) in liners.data" :key="liner.id" class="hover:bg-blue-50/50">
                                <td class="px-5 py-4 text-gray-500">{{ liners.from + index }}</td>
                                <td class="max-w-xs px-5 py-4 font-semibold break-words text-gray-800">{{ liner.name }}</td>
                                <td class="max-w-xs px-5 py-4 break-words text-gray-600">{{ liner.route }}</td>
                                <td class="px-5 py-4 text-center">
                                    <button type="button" title="Edit liner" :aria-label="`Edit ${liner.name}`"
                                        class="rounded-lg bg-[#E3F2FD] p-2 text-[#0D47A1] hover:bg-[#90CAF9]" @click="openForm(liner)">
                                        <Pencil aria-hidden="true" class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!liners.data.length">
                                <td colspan="4" class="px-5 py-16 text-center">
                                    <Route aria-hidden="true" class="mx-auto mb-3 h-10 w-10 text-[#2196F3]" />
                                    <p class="font-medium text-gray-700">{{ loading ? 'Loading liners...' : 'No liners found' }}</p>
                                    <p v-if="!loading" class="mt-1 text-gray-500">{{ search ? 'Try another search.' : 'Add a liner to get started.' }}</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col gap-3 border-t border-[#E3F2FD] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-gray-500">Showing {{ liners.from ?? 0 }} to {{ liners.to ?? 0 }} of {{ liners.total }} liners</p>
                    <nav aria-label="Liner pagination" class="flex items-center gap-3 text-sm text-[#0D47A1]">
                        <button type="button" :disabled="loading || !liners.prev_page_url" aria-label="Previous page"
                            class="rounded-lg border border-[#90CAF9] p-2 disabled:opacity-40" @click="loadLiners(liners.current_page - 1)">
                            <ChevronLeft aria-hidden="true" class="h-4 w-4" />
                        </button>
                        <span>Page {{ liners.current_page }} of {{ liners.last_page }}</span>
                        <button type="button" :disabled="loading || !liners.next_page_url" aria-label="Next page"
                            class="rounded-lg border border-[#90CAF9] p-2 disabled:opacity-40" @click="loadLiners(liners.current_page + 1)">
                            <ChevronRight aria-hidden="true" class="h-4 w-4" />
                        </button>
                    </nav>
                </div>
            </div>
        </div>

        <LinerForm v-model:open="formOpen" :liner="selectedLiner" @saved="saved" />
    </AuthenticatedLayout>
</template>

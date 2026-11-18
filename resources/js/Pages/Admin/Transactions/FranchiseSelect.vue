<script setup>
import { inject, onBeforeUnmount, ref, watch } from 'vue'
import http from '@/lib/http'
const selected = defineModel({ default: null })
const props = defineProps({ error: String, searchRoute: { type: String, default: 'transactions.franchises' } })
const route = inject('route')
const search = ref(selected.value?.franchise_number ?? '')
const results = ref([])
const expanded = ref(false)
const loading = ref(false)
const loadError = ref('')
const active = ref(-1)
let timer
let request
async function load() {
    request?.abort()
    const current = new AbortController()
    request = current
    loading.value = true
    loadError.value = ''
    try {
        const { data } = await http.get(route(props.searchRoute), { params: { search: search.value }, signal: current.signal })
        if (!current.signal.aborted) { results.value = data.franchises; active.value = -1 }
    } catch (error) {
        if (!current.signal.aborted) loadError.value = 'Unable to search franchises.'
    } finally { if (request === current) loading.value = false }
}
function input(event) {
    search.value = event.target.value
    selected.value = null
    results.value = []
    expanded.value = true
    request?.abort()
    clearTimeout(timer)
    timer = setTimeout(load, 250)
}
function choose(franchise) {
    selected.value = franchise
    search.value = franchise.franchise_number
    expanded.value = false
}
function focus() { expanded.value = true; load() }
function keydown(event) {
    if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); expanded.value = false }
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault()
        expanded.value = true
        active.value = Math.max(0, Math.min(results.value.length - 1, active.value + (event.key === 'ArrowDown' ? 1 : -1)))
    }
    if (event.key === 'Enter') {
        event.preventDefault()
        if (expanded.value && results.value[active.value]) choose(results.value[active.value])
    }
}
watch(selected, value => { if (value) search.value = value.franchise_number })
onBeforeUnmount(() => { clearTimeout(timer); request?.abort() })
</script>
<template>
    <div class="relative" @focusout="event => { if (!event.currentTarget.contains(event.relatedTarget)) expanded = false }">
        <label for="transaction-franchise-search" class="mb-2 block text-sm font-medium text-gray-700">Franchise number</label>
        <input id="transaction-franchise-search" :value="search" type="search" role="combobox" autocomplete="off" maxlength="50" placeholder="Search franchise number..." aria-autocomplete="list" aria-controls="transaction-franchise-results" :aria-expanded="expanded" :aria-activedescendant="expanded && active >= 0 ? `franchise-option-${active}` : undefined" :aria-invalid="!!error" aria-describedby="transaction-franchise-error" class="w-full rounded-lg border border-[#90CAF9] px-3 py-2.5 text-sm" @input="input" @focus="focus" @keydown="keydown" />
        <div v-if="expanded" class="absolute z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-blue-100 bg-white shadow-lg">
            <p v-if="loading" role="status" class="p-3 text-sm text-gray-500">Searching...</p>
            <p v-if="loadError" role="alert" class="p-3 text-sm text-red-600">{{ loadError }} <button type="button" class="underline" @click="load">Retry</button></p>
            <ul id="transaction-franchise-results" role="listbox" aria-label="Franchise numbers">
                <li v-for="(franchise, index) in results" :id="`franchise-option-${index}`" :key="franchise.id" role="option" :aria-selected="selected?.id === franchise.id" class="cursor-pointer px-3 py-2 text-sm hover:bg-blue-50" :class="{ 'bg-blue-50': active === index }" @mousedown.prevent @click="choose(franchise)">{{ franchise.franchise_number }}</li>
            </ul>
            <p v-if="!loading && !loadError && !results.length" class="p-3 text-sm text-gray-500">No franchises found.</p>
        </div>
        <p v-if="selected" class="mt-1 text-xs text-green-700">Selected: {{ selected.franchise_number }}</p>
        <p v-if="error" id="transaction-franchise-error" role="alert" class="mt-1 text-sm text-red-600">{{ error }}</p>
    </div>
</template>

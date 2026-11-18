<script setup>
import { computed, ref, watch, watchEffect } from 'vue'

const selected = defineModel()
const props = defineProps({ owners: { type: Array, required: true }, error: String })
const input = ref(null)
const search = ref('')
const expanded = ref(false)
const active = ref(-1)
const selectedOwner = computed(() => props.owners.find(owner => owner.value === selected.value))
const matches = computed(() => {
    const terms = (selectedOwner.value ? '' : search.value).trim().toLocaleLowerCase().split(/\s+/)
    return props.owners.filter(owner => terms.every(term => owner.label.toLocaleLowerCase().includes(term)))
})

watch(selectedOwner, owner => {
    if (owner) {
        search.value = owner.label
        close()
    } else if (selected.value) {
        selected.value = ''
        search.value = ''
    }
}, { immediate: true })

watchEffect(() => input.value?.setCustomValidity(selectedOwner.value ? '' : 'Select an existing owner or add a new owner.'))
watch(active, index => {
    const owner = matches.value[index]
    if (owner) document.getElementById(`transaction-owner-option-${owner.value}`)?.scrollIntoView({ block: 'nearest' })
}, { flush: 'post' })

function close() {
    expanded.value = false
    active.value = -1
}

function changeSearch(event) {
    search.value = event.target.value
    selected.value = ''
    expanded.value = true
    active.value = -1
}

function choose(owner) {
    selected.value = owner.value
    search.value = owner.label
    close()
}

function keydown(event) {
    if (event.key === 'Escape' && expanded.value) {
        event.preventDefault()
        event.stopPropagation()
        close()
    } else if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
        event.preventDefault()
        expanded.value = true
        if (!matches.value.length) return
        active.value = active.value < 0 && event.key === 'ArrowUp'
            ? matches.value.length - 1
            : Math.max(0, Math.min(matches.value.length - 1, active.value + (event.key === 'ArrowDown' ? 1 : -1)))
    } else if (event.key === 'Enter' && expanded.value) {
        event.preventDefault()
        const owner = matches.value[active.value] ?? (matches.value.length === 1 ? matches.value[0] : null)
        if (owner) choose(owner)
    }
}
</script>

<template>
    <div class="relative">
        <label for="transaction-new-owner" class="mb-2 block text-sm font-medium text-gray-700">New owner</label>
        <input id="transaction-new-owner" ref="input" :value="search" type="search" role="combobox" autocomplete="off" placeholder="Type an owner's name" aria-autocomplete="list" :aria-expanded="expanded" :aria-controls="expanded ? 'transaction-new-owner-options' : undefined" :aria-activedescendant="expanded && matches[active] ? `transaction-owner-option-${matches[active].value}` : undefined" :aria-invalid="!!error" :aria-describedby="error ? 'transaction-new-owner-error' : undefined" required class="w-full rounded-lg border border-[#90CAF9] px-3 py-2.5 text-sm" @input="changeSearch" @focus="expanded = true" @click="expanded = true" @blur="close" @keydown="keydown" />
        <div v-if="expanded" class="absolute z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-blue-100 bg-white shadow-lg">
            <ul id="transaction-new-owner-options" role="listbox" aria-label="Matching owners">
                <li v-for="(owner, index) in matches" :id="`transaction-owner-option-${owner.value}`" :key="owner.value" role="option" :aria-selected="selected === owner.value" class="cursor-pointer px-3 py-2 text-sm hover:bg-blue-50" :class="{ 'bg-blue-50': index === active }" @mousedown.prevent @click="choose(owner)">{{ owner.label }}</li>
            </ul>
            <p v-if="!matches.length" role="status" class="p-3 text-sm text-gray-500">No matching owners.</p>
        </div>
        <p v-if="error" id="transaction-new-owner-error" role="alert" class="mt-1 text-sm text-red-600">{{ error }}</p>
    </div>
</template>

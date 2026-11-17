<script setup>
import { computed } from 'vue'

const selected = defineModel({ type: Array, default: () => [] })
const props = defineProps({
    permissions: { type: Array, default: () => [] },
    disabled: Boolean,
    errors: { type: Object, default: () => ({}) },
})
const errors = computed(() => Object.entries(props.errors)
    .filter(([key]) => key === 'permissions' || key.startsWith('permissions.'))
    .map(([, message]) => message))
</script>

<template>
    <fieldset :disabled="disabled">
        <legend class="mb-2 text-sm font-medium text-gray-700">Permissions</legend>
        <p class="mb-3 text-xs text-gray-500">Select the permissions this role should grant.</p>
        <div v-if="permissions.length" class="max-h-60 space-y-2 overflow-y-auto rounded-lg border border-[#90CAF9] p-3">
            <label v-for="permission in permissions" :key="permission.id" class="flex cursor-pointer items-center gap-3 text-sm text-gray-700">
                <input v-model="selected" type="checkbox" :value="permission.id" class="h-4 w-4 accent-[#2196F3]" />
                <span>{{ permission.name }}</span>
            </label>
        </div>
        <p v-else class="text-sm text-gray-500">No permissions available. Create permissions first to assign them to a role.</p>
        <p v-for="(error, index) in errors" :key="index" role="alert" class="mt-2 text-sm text-red-600">{{ error }}</p>
    </fieldset>
</template>

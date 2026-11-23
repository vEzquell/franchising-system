<script setup>
import { computed, inject, nextTick, reactive, ref, watch } from 'vue'
import { UserPlus, X } from 'lucide-vue-next'
import http from '@/lib/http'

const props = defineProps({
    roles: { type: Array, default: () => [] },
    user: { type: Object, default: null },
})
const open = defineModel('open', { type: Boolean, default: false })
const emit = defineEmits(['created', 'updated'])
const editing = computed(() => !!props.user)
const title = computed(() => editing.value ? 'Edit User' : 'Add User')
const fieldPrefix = computed(() => editing.value ? 'edit-user' : 'new-user')
const route = inject('route')
const dialog = ref(null)
const nameInput = ref(null)
const initial = () => ({
    name: props.user?.name ?? '', username: props.user?.username ?? '',
    password: '', password_confirmation: '',
    role_id: props.user?.roles?.[0]?.id ?? '', status: props.user?.status ?? 'active',
})
const form = reactive(initial())
const errors = ref({})
const errorMessage = ref('')
const processing = ref(false)

watch(open, async (value) => {
    await nextTick()
    if (value) {
        Object.assign(form, initial())
        errors.value = {}
        errorMessage.value = ''
        if (!dialog.value.open) dialog.value.showModal()
        nameInput.value?.focus()
    } else {
        dialog.value.close()
        form.password = ''
        form.password_confirmation = ''
    }
})

function close() {
    if (!processing.value) open.value = false
}

function closeOnBackdrop(event) {
    if (event.target !== dialog.value) return
    const bounds = dialog.value.getBoundingClientRect()
    if (event.clientX < bounds.left || event.clientX > bounds.right
        || event.clientY < bounds.top || event.clientY > bounds.bottom) close()
}

async function submit() {
    if (processing.value) return
    processing.value = true
    errors.value = {}
    errorMessage.value = ''
    try {
        await http.post(editing.value ? route('users.update', props.user.id) : route('users.store'), { ...form })
        open.value = false
        Object.assign(form, initial())
        emit(editing.value ? 'updated' : 'created')
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = Object.fromEntries(Object.entries(error.response.data.errors ?? {})
                .map(([field, messages]) => [field, Array.isArray(messages) ? messages[0] : messages]))
        } else {
            errorMessage.value = error.response?.status === 419 ? 'Your session expired. Refresh the page and try again.'
                : error.response?.status === 401 ? 'Your session ended. Sign in again.'
                    : error.response?.status === 403 ? 'You do not have permission to manage users.'
                        : 'Unable to save the user. Please try again.'
        }
    } finally {
        processing.value = false
    }
}
</script>

<template>
    <Teleport to="body">
        <dialog ref="dialog" :aria-labelledby="`${fieldPrefix}-title`" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-xl border border-[#90CAF9]/50 bg-white p-0 shadow-2xl backdrop:bg-black/50" @cancel.prevent="close" @close="open = false" @click="closeOnBackdrop">
            <div class="flex items-start justify-between gap-4 border-b border-[#E3F2FD] p-5">
                <div>
                    <div class="flex items-center gap-2"><UserPlus class="h-6 w-6 text-[#0D47A1]" /><h2 :id="`${fieldPrefix}-title`" class="text-xl font-bold text-[#0D47A1]">{{ title }}</h2></div>
                    <p class="mt-1 text-sm text-gray-500">{{ editing ? 'Update account details, role, status, or password.' : 'Create an account and assign its role.' }}</p>
                </div>
                <button type="button" :aria-label="`Close ${title}`" :disabled="processing" class="rounded-lg p-2 text-gray-500 hover:bg-blue-50 disabled:opacity-50" @click="close"><X class="h-5 w-5" /></button>
            </div>
            <form class="space-y-4 p-5" @submit.prevent="submit">
                <p v-if="errorMessage" role="alert" class="text-sm text-red-600">{{ errorMessage }}</p>
                <div>
                    <label :for="`${fieldPrefix}-name`" class="mb-1.5 block text-sm font-medium text-gray-700">Full name</label>
                    <input v-uppercase :id="`${fieldPrefix}-name`" ref="nameInput" v-model="form.name" required maxlength="150" autocomplete="name" :disabled="processing" :aria-invalid="!!errors.name" :aria-describedby="`${fieldPrefix}-name-error`" class="w-full rounded-lg border border-[#90CAF9] px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-[#90CAF9]/50" />
                    <p :id="`${fieldPrefix}-name-error`" class="mt-1 text-sm text-red-600">{{ errors.name }}</p>
                </div>
                <div>
                    <label :for="`${fieldPrefix}-username`" class="mb-1.5 block text-sm font-medium text-gray-700">Username</label>
                    <input :id="`${fieldPrefix}-username`" v-model="form.username" required maxlength="50" autocomplete="off" :disabled="processing" :aria-invalid="!!errors.username" :aria-describedby="`${fieldPrefix}-username-error`" class="w-full rounded-lg border border-[#90CAF9] px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-[#90CAF9]/50" />
                    <p class="mt-1 text-xs text-gray-500">Letters, numbers, underscores, and hyphens only.</p>
                    <p :id="`${fieldPrefix}-username-error`" class="mt-1 text-sm text-red-600">{{ errors.username }}</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div v-for="field in [{ key: 'password', label: 'Password' }, { key: 'password_confirmation', label: 'Confirm password' }]" :key="field.key">
                        <label :for="`${fieldPrefix}-${field.key}`" class="mb-1.5 block text-sm font-medium text-gray-700">{{ field.label }}</label>
                        <input :id="`${fieldPrefix}-${field.key}`" v-model="form[field.key]" type="password" :required="!editing || !!form.password || !!form.password_confirmation" minlength="8" maxlength="255" autocomplete="new-password" :disabled="processing" :aria-invalid="!!errors[field.key]" :aria-describedby="`${fieldPrefix}-${field.key}-error`" class="w-full rounded-lg border border-[#90CAF9] px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-[#90CAF9]/50" />
                        <p :id="`${fieldPrefix}-${field.key}-error`" class="mt-1 text-sm text-red-600">{{ errors[field.key] }}</p>
                    </div>
                </div>
                <p class="text-xs text-gray-500">{{ editing ? 'Leave both password fields blank to keep the current password. New passwords need at least 8 characters.' : 'Use a password with at least 8 characters.' }}</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label :for="`${fieldPrefix}-role`" class="mb-1.5 block text-sm font-medium text-gray-700">Role</label>
                        <select :id="`${fieldPrefix}-role`" v-model="form.role_id" required :disabled="processing" :aria-invalid="!!errors.role_id" :aria-describedby="`${fieldPrefix}-role-error`" class="w-full rounded-lg border border-[#90CAF9] bg-white px-3 py-2.5 text-sm"><option disabled value="">Select a role</option><option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option></select>
                        <p :id="`${fieldPrefix}-role-error`" class="mt-1 text-sm text-red-600">{{ errors.role_id }}</p>
                    </div>
                    <div>
                        <label :for="`${fieldPrefix}-status`" class="mb-1.5 block text-sm font-medium text-gray-700">Status</label>
                        <select :id="`${fieldPrefix}-status`" v-model="form.status" required :disabled="processing" :aria-invalid="!!errors.status" :aria-describedby="`${fieldPrefix}-status-error`" class="w-full rounded-lg border border-[#90CAF9] bg-white px-3 py-2.5 text-sm"><option value="active">Active</option><option value="inactive">Inactive</option></select>
                        <p :id="`${fieldPrefix}-status-error`" class="mt-1 text-sm text-red-600">{{ errors.status }}</p>
                    </div>
                </div>
                <p v-if="!roles.length" role="status" class="text-sm text-amber-700">Create a role in Role Management before adding a user.</p>
                <div class="flex justify-end gap-3 border-t border-[#E3F2FD] pt-4">
                    <button type="button" :disabled="processing" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm text-gray-600 disabled:opacity-50" @click="close">Cancel</button>
                    <button type="submit" :disabled="processing || !roles.length" class="rounded-lg bg-[#2196F3] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#0D47A1] disabled:opacity-50">{{ processing ? 'Saving...' : 'Save User' }}</button>
                </div>
            </form>
        </dialog>
    </Teleport>
</template>


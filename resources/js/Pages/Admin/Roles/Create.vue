<script setup>
import { inject, nextTick, ref, watch } from 'vue'
import { useRoleForm } from '@/Composables/useRoleForm'
import RolePermissions from './RolePermissions.vue'
import { Shield, X } from 'lucide-vue-next'

defineProps({ permissions: { type: Array, default: () => [] } })

const open = defineModel('open', { type: Boolean, default: false })
const emit = defineEmits(['created'])
const route = inject('route')
const dialog = ref(null)
const nameInput = ref(null)
const form = useRoleForm()

watch(open, async (value) => {
    await nextTick()
    if (value) {
        form.reset()
        form.clearErrors()
        if (!dialog.value.open) dialog.value.showModal()
        nameInput.value?.focus()
    } else {
        dialog.value.close()
    }
})

function close() {
    if (!form.processing) open.value = false
}

function closeOnBackdrop(event) {
    if (event.target !== dialog.value) return
    const bounds = dialog.value.getBoundingClientRect()
    if (event.clientX < bounds.left || event.clientX > bounds.right
        || event.clientY < bounds.top || event.clientY > bounds.bottom) close()
}

async function submit() {
    if (await form.submit('post', route('roles.store'))) {
        open.value = false
        form.reset()
        emit('created')
    }
}
</script>

<template>
    <Teleport to="body">
        <dialog ref="dialog" aria-labelledby="create-role-title" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-md overflow-y-auto rounded-xl border border-[#90CAF9]/50 bg-white p-0 shadow-2xl backdrop:bg-black/50" @cancel.prevent="close" @close="open = false" @click="closeOnBackdrop">
            <div class="flex items-start justify-between gap-4 border-b border-[#E3F2FD] p-5">
                <div>
                    <div class="flex items-center gap-2">
                        <Shield class="h-6 w-6 text-[#0D47A1]" />
                        <h2 id="create-role-title" class="text-xl font-bold text-[#0D47A1]">Add Role</h2>
                    </div>
                    <p class="mt-1 text-sm text-gray-500">Create a role for system access controls.</p>
                </div>
                <button type="button" aria-label="Close Add Role" :disabled="form.processing" class="rounded-lg p-2 text-gray-500 hover:bg-blue-50 disabled:opacity-50" @click="close"><X class="h-5 w-5" /></button>
            </div>

            <form class="space-y-6 p-5" @submit.prevent="submit">
                <p v-if="form.error" role="alert" class="text-sm text-red-600">{{ form.error }}</p>
                <div>
                    <label for="create-role-name" class="mb-2 block text-sm font-medium text-gray-700">Role name <span aria-hidden="true" class="text-red-600">*</span></label>
                    <input id="create-role-name" ref="nameInput" v-model="form.name" type="text" required maxlength="255" :disabled="form.processing" :aria-invalid="!!form.errors.name" aria-describedby="create-role-help create-role-error" placeholder="e.g. cashier" class="w-full rounded-lg border border-[#90CAF9] px-3 py-2.5 text-sm text-gray-700 outline-none focus:border-[#2196F3] focus:ring-2 focus:ring-[#90CAF9]/30 disabled:opacity-50" />
                    <p id="create-role-help" class="mt-2 text-xs text-gray-500">Use a unique name. This role uses the web guard.</p>
                    <p id="create-role-error" role="alert" class="mt-2 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>

                <RolePermissions v-model="form.permissions" :permissions="permissions" :disabled="form.processing" :errors="form.errors" />

                <div class="flex justify-end gap-3 border-t border-[#E3F2FD] pt-5">
                    <button type="button" :disabled="form.processing" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-50" @click="close">Cancel</button>
                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-[#2196F3] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#0D47A1] disabled:opacity-50">{{ form.processing ? 'Saving...' : 'Save Role' }}</button>
                </div>
            </form>
        </dialog>
    </Teleport>
</template>


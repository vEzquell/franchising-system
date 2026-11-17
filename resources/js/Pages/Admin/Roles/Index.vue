<script setup>
    import { inject, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
    import { Head } from '@inertiajs/vue3'
    import http from '@/lib/http'
    import { useRoleForm } from '@/Composables/useRoleForm'
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
    import CreateRole from './Create.vue'
    import RolePermissions from './RolePermissions.vue'
    import { Shield, Search, Plus, Pencil, Trash2, X, ChevronLeft, ChevronRight } from 'lucide-vue-next'

    const props = defineProps({
        roles: { type: Object, required: true },
        permissions: { type: Array, default: () => [] },
        filters: { type: Object, default: () => ({ search: '' }) },
        success: { type: String, default: null },
    })
    const route = inject('route')
    const search = ref(props.filters.search)
    const notice = ref('')
    const createOpen = ref(false)
    onMounted(() => {
        if (new URLSearchParams(window.location.search).get('create') === '1') {
            createOpen.value = true
        }
    })
    const dialog = ref(null)
    const nameInput = ref(null)
    const mode = ref('edit')
    const selected = ref(null)
    const form = useRoleForm()
    const roles = ref(props.roles)
    const loading = ref(false)
    const loadError = ref('')
    let searchTimer
    let listRequest

    async function loadRoles(page = 1) {
        clearTimeout(searchTimer)
        listRequest?.abort()
        const request = new AbortController()
        listRequest = request
        loading.value = true
        loadError.value = ''
        try {
            const { data } = await http.get(route('roles.index'), {
                params: { search: search.value, page }, signal: request.signal,
            })
            if (request.signal.aborted) return
            if (data.roles.data.length === 0 && page > data.roles.last_page) {
                await loadRoles(data.roles.last_page)
                return
            }
            roles.value = data.roles
        } catch (error) {
            if (!request.signal.aborted) loadError.value = 'Unable to load roles. Please try again.'
        } finally {
            if (listRequest === request) loading.value = false
        }
    }

    watch(search, () => {
        clearTimeout(searchTimer)
        listRequest?.abort()
        searchTimer = setTimeout(() => loadRoles(), 300)
    })
    watch(() => props.filters.search, (value) => {
        if (search.value !== value) search.value = value
    })
    watch(() => props.roles, (value) => { roles.value = value })
    onBeforeUnmount(() => { clearTimeout(searchTimer); listRequest?.abort() })

    async function openDialog(action, role = null) {
        mode.value = action
        selected.value = role
        form.reset()
        form.clearErrors()
        form.name = role?.name ?? ''
        form.permissions = role?.permissions?.map(permission => permission.id) ?? []
        await nextTick()
        dialog.value.showModal()
        if (action !== 'delete') nameInput.value?.focus()
    }

    function closeDialog() {
        if (!form.processing) dialog.value.close()
    }

    async function submit() {
        const deleting = mode.value === 'delete'
        if (await form.submit(deleting ? 'delete' : 'post', route(deleting ? 'roles.destroy' : 'roles.update', selected.value.id))) {
            dialog.value.close()
            notice.value = deleting ? 'Role deleted.' : 'Role updated.'
            form.reset()
            await loadRoles(roles.value.current_page)
        }
    }

    function roleCreated() {
        notice.value = 'Role created.'
        loadRoles(roles.value.current_page)
    }

    const formatDate = (value) => value ? new Date(value).toLocaleDateString() : '—'
</script>

<template>
    <Head title="Role Management" />
    <AuthenticatedLayout>
        <div class="min-h-full bg-[#E3F2FD]/30 p-4 md:p-6">
            <p v-if="notice || success" role="status" class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ notice || success }}</p>
            <p v-if="loadError" role="alert" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ loadError }} <button type="button" class="underline" @click="loadRoles(roles.current_page)">Retry</button></p>
            <p v-if="loading" role="status" class="mb-3 text-sm text-gray-500">Loading roles...</p>
            <div class="overflow-hidden rounded-xl border border-[#90CAF9]/50 bg-white shadow-sm">
                <div class="flex flex-col gap-4 border-b border-[#E3F2FD] p-5 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <Shield class="h-6 w-6 text-[#0D47A1]" />
                            <h1 class="text-xl font-bold text-[#0D47A1]">Role Management</h1>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">Manage system roles and access controls.</p>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#2196F3]" />
                            <input v-model="search" aria-label="Search roles" type="search" maxlength="255" placeholder="Search roles..." class="w-full rounded-lg border border-[#90CAF9] bg-white py-2.5 pl-10 pr-4 text-sm text-gray-700 outline-none focus:border-[#2196F3] focus:ring-2 focus:ring-[#90CAF9]/30 sm:w-64" />
                        </div>
                        <button  type="button" class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#2196F3] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#0D47A1]" @click="createOpen = true">
                            <Plus class="h-4 w-4" /> Add Role
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto" :aria-busy="loading">
                    <table class="w-full min-w-[750px] text-left text-sm">
                        <thead class="bg-[#E3F2FD] text-[#0D47A1]">
                            <tr>
                                <th scope="col" class="px-5 py-4 font-semibold">#</th>
                                <th scope="col" class="px-5 py-4 font-semibold">Role</th>
                                <th scope="col" class="px-5 py-4 font-semibold">Guard</th>
                                <th scope="col" class="px-5 py-4 font-semibold">Assignments</th>
                                <th scope="col" class="px-5 py-4 font-semibold">Created</th>
                                <th  scope="col" class="px-5 py-4 text-center font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E3F2FD]">
                            <tr v-for="(role, index) in roles.data" :key="role.id" class="hover:bg-blue-50/50">
                                <td class="px-5 py-4 text-gray-500">{{ roles.from + index }}</td>
                                <td class="px-5 py-4 font-medium text-gray-800">{{ role.name }}</td>
                                <td class="px-5 py-4"><span class="rounded-full bg-[#E3F2FD] px-3 py-1 text-xs font-semibold text-[#0D47A1]">{{ role.guard_name }}</span></td>
                                <td class="px-5 py-4 text-gray-500">{{ role.permissions_count }} permissions · {{ role.users_count }} direct users</td>
                                <td class="px-5 py-4 text-gray-500">{{ formatDate(role.created_at) }}</td>
                                <td  class="px-5 py-4">
                                    <div class="flex justify-center gap-2">
                                        <button type="button" :aria-label="`Edit ${role.name}`" class="inline-flex items-center gap-1.5 rounded-lg bg-[#E3F2FD] px-3 py-2 text-xs font-semibold text-[#0D47A1] hover:bg-[#90CAF9]" @click="openDialog('edit', role)"><Pencil class="h-3.5 w-3.5" /> Edit</button>
                                        <button type="button" :aria-label="`Delete ${role.name}`" class="inline-flex items-center gap-1.5 rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100" @click="openDialog('delete', role)"><Trash2 class="h-3.5 w-3.5" /> Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="roles.data.length === 0">
                                <td colspan="6" class="px-5 py-16 text-center">
                                    <Shield class="mx-auto mb-3 h-10 w-10 text-[#2196F3]" />
                                    <p class="font-medium text-gray-700">No roles found</p>
                                    <p class="mt-1 text-gray-500">{{ filters.search ? 'Try another search.' : 'Roles will appear here once created.' }}</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-col gap-3 border-t border-[#E3F2FD] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-gray-500">Showing {{ roles.from ?? 0 }} to {{ roles.to ?? 0 }} of {{ roles.total }} roles</p>
                    <nav aria-label="Role pagination" class="flex items-center gap-3 text-sm text-[#0D47A1]">
                        <button v-if="roles.prev_page_url" type="button" :disabled="loading" class="rounded-lg border border-[#90CAF9] p-2 hover:bg-[#E3F2FD] disabled:opacity-50" aria-label="Previous page" @click="loadRoles(roles.current_page - 1)"><ChevronLeft class="h-4 w-4" /></button>
                        <button v-else disabled aria-label="Previous page" class="rounded-lg border border-gray-200 p-2 text-gray-300"><ChevronLeft class="h-4 w-4" /></button>
                        <span>Page {{ roles.current_page }} of {{ roles.last_page }}</span>
                        <button v-if="roles.next_page_url" type="button" :disabled="loading" class="rounded-lg border border-[#90CAF9] p-2 hover:bg-[#E3F2FD] disabled:opacity-50" aria-label="Next page" @click="loadRoles(roles.current_page + 1)"><ChevronRight class="h-4 w-4" /></button>
                        <button v-else disabled aria-label="Next page" class="rounded-lg border border-gray-200 p-2 text-gray-300"><ChevronRight class="h-4 w-4" /></button>
                    </nav>
                </div>
            </div>
        </div>
        <dialog ref="dialog" aria-labelledby="role-dialog-title" class="m-auto w-[calc(100%-2rem)] max-w-md max-h-[calc(100dvh-2rem)] overflow-y-auto rounded-xl border border-blue-100 bg-white p-6 shadow-xl backdrop:bg-black/50" @cancel.prevent="closeDialog">
            <form @submit.prevent="submit">
                <p v-if="form.error" role="alert" class="mb-3 text-sm text-red-600">{{ form.error }}</p>
                <div class="mb-4 flex items-center justify-between gap-4">
                    <h2 id="role-dialog-title" class="text-lg font-bold text-[#0D47A1]">{{ mode === 'delete' ? 'Delete Role' : 'Edit Role' }}</h2>
                    <button type="button" aria-label="Close dialog" :disabled="form.processing" class="rounded-lg p-2 hover:bg-blue-50 disabled:opacity-50" @click="closeDialog"><X class="h-5 w-5" /></button>
                </div>
                <p v-if="mode === 'delete'" class="text-sm text-gray-600">Delete “{{ selected?.name }}”? This cannot be undone. Roles assigned to users cannot be deleted.</p>
                <div v-else>
                    <label for="role-name" class="mb-2 block text-sm font-medium text-gray-700">Role name</label>
                    <input id="role-name" ref="nameInput" v-model="form.name" required maxlength="255" :disabled="form.processing" :aria-invalid="!!form.errors.name" aria-describedby="role-name-help role-name-error" class="w-full rounded-lg border border-[#90CAF9] px-3 py-2.5 text-sm focus:outline-2 focus:outline-[#2196F3]" placeholder="e.g. cashier" />
                    <p id="role-name-help" class="mt-2 text-xs text-gray-500">Use a unique name. This role uses the web guard.</p>
                    <p id="role-name-error" class="mt-2 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>
                <RolePermissions v-if="mode !== 'delete'" v-model="form.permissions" :permissions="permissions" :disabled="form.processing" :errors="form.errors" class="mt-4" />
                <p v-if="form.errors.role" role="alert" class="mt-3 text-sm text-red-600">{{ form.errors.role }}</p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" :disabled="form.processing" class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600 disabled:opacity-50" @click="closeDialog">Cancel</button>
                    <button type="submit" :disabled="form.processing" class="rounded-lg px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :class="mode === 'delete' ? 'bg-red-600 hover:bg-red-700' : 'bg-[#2196F3] hover:bg-[#0D47A1]'">{{ form.processing ? 'Saving...' : mode === 'delete' ? 'Delete Role' : 'Save Role' }}</button>
                </div>
            </form>
        </dialog>
        <CreateRole  v-model:open="createOpen" :permissions="permissions" @created="roleCreated" />
    </AuthenticatedLayout>
</template>


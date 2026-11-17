<script setup>
    import { inject, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
    import { Head } from '@inertiajs/vue3'
    import http from '@/lib/http'
    import { usePermissionForm } from '@/Composables/usePermissionForm'
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
    import CreatePermission from './Create.vue'
    import { ShieldCheck, Search, Plus, Pencil, Trash2, X, ChevronLeft, ChevronRight } from 'lucide-vue-next'

    const props = defineProps({
        permissions: { type: Object, required: true },
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
    const form = usePermissionForm()
    const permissions = ref(props.permissions)
    const loading = ref(false)
    const loadError = ref('')
    let searchTimer
    let listRequest

    async function loadPermissions(page = 1) {
        clearTimeout(searchTimer)
        listRequest?.abort()
        const request = new AbortController()
        listRequest = request
        loading.value = true
        loadError.value = ''
        try {
            const { data } = await http.get(route('permissions.index'), {
                params: { search: search.value, page }, signal: request.signal,
            })
            if (request.signal.aborted) return
            if (data.permissions.data.length === 0 && page > data.permissions.last_page) {
                await loadPermissions(data.permissions.last_page)
                return
            }
            permissions.value = data.permissions
        } catch (error) {
            if (!request.signal.aborted) loadError.value = 'Unable to load permissions. Please try again.'
        } finally {
            if (listRequest === request) loading.value = false
        }
    }

    watch(search, () => {
        clearTimeout(searchTimer)
        listRequest?.abort()
        searchTimer = setTimeout(() => loadPermissions(), 300)
    })
    watch(() => props.filters.search, (value) => {
        if (search.value !== value) search.value = value
    })
    watch(() => props.permissions, (value) => { permissions.value = value })
    onBeforeUnmount(() => { clearTimeout(searchTimer); listRequest?.abort() })

    async function openDialog(action, permission = null) {
        mode.value = action
        selected.value = permission
        form.reset()
        form.clearErrors()
        form.name = permission?.name ?? ''
        await nextTick()
        dialog.value.showModal()
        if (action !== 'delete') nameInput.value?.focus()
    }

    function closeDialog() {
        if (!form.processing) dialog.value.close()
    }

    async function submit() {
        const deleting = mode.value === 'delete'
        if (await form.submit(deleting ? 'delete' : 'post', route(deleting ? 'permissions.destroy' : 'permissions.update', selected.value.id))) {
            dialog.value.close()
            notice.value = deleting ? 'Permission deleted.' : 'Permission updated.'
            form.reset()
            await loadPermissions(permissions.value.current_page)
        }
    }

    function permissionCreated() {
        notice.value = 'Permission created.'
        loadPermissions(permissions.value.current_page)
    }

    const formatDate = (value) => value ? new Date(value).toLocaleDateString() : '—'
</script>

<template>
    <Head title="Permission Management" />
    <AuthenticatedLayout>
        <div class="min-h-full bg-[#E3F2FD]/30 p-4 md:p-6">
            <p v-if="notice || success" role="status" class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ notice || success }}</p>
            <p v-if="loadError" role="alert" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ loadError }} <button type="button" class="underline" @click="loadPermissions(permissions.current_page)">Retry</button></p>
            <p v-if="loading" role="status" class="mb-3 text-sm text-gray-500">Loading permissions...</p>
            <div class="overflow-hidden rounded-xl border border-[#90CAF9]/50 bg-white shadow-sm">
                <div class="flex flex-col gap-4 border-b border-[#E3F2FD] p-5 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <ShieldCheck class="h-6 w-6 text-[#0D47A1]" />
                            <h1 class="text-xl font-bold text-[#0D47A1]">Permission Management</h1>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">Manage system permissions and access controls.</p>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#2196F3]" />
                            <input v-model="search" aria-label="Search permissions" type="search" maxlength="255" placeholder="Search permissions..." class="w-full rounded-lg border border-[#90CAF9] bg-white py-2.5 pl-10 pr-4 text-sm text-gray-700 outline-none focus:border-[#2196F3] focus:ring-2 focus:ring-[#90CAF9]/30 sm:w-64" />
                        </div>
                        <button  type="button" class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#2196F3] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#0D47A1]" @click="createOpen = true">
                            <Plus class="h-4 w-4" /> Add Permission
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto" :aria-busy="loading">
                    <table class="w-full min-w-[750px] text-left text-sm">
                        <thead class="bg-[#E3F2FD] text-[#0D47A1]">
                            <tr>
                                <th scope="col" class="px-5 py-4 font-semibold">#</th>
                                <th scope="col" class="px-5 py-4 font-semibold">Permission</th>
                                <th scope="col" class="px-5 py-4 font-semibold">Guard</th>
                                <th scope="col" class="px-5 py-4 font-semibold">Assignments</th>
                                <th scope="col" class="px-5 py-4 font-semibold">Created</th>
                                <th  scope="col" class="px-5 py-4 text-center font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E3F2FD]">
                            <tr v-for="(permission, index) in permissions.data" :key="permission.id" class="hover:bg-blue-50/50">
                                <td class="px-5 py-4 text-gray-500">{{ permissions.from + index }}</td>
                                <td class="px-5 py-4 font-medium text-gray-800">{{ permission.name }}</td>
                                <td class="px-5 py-4"><span class="rounded-full bg-[#E3F2FD] px-3 py-1 text-xs font-semibold text-[#0D47A1]">{{ permission.guard_name }}</span></td>
                                <td class="px-5 py-4 text-gray-500">{{ permission.roles_count }} roles · {{ permission.users_count }} direct users</td>
                                <td class="px-5 py-4 text-gray-500">{{ formatDate(permission.created_at) }}</td>
                                <td  class="px-5 py-4">
                                    <div class="flex justify-center gap-2">
                                        <button type="button" :aria-label="`Edit ${permission.name}`" class="inline-flex items-center gap-1.5 rounded-lg bg-[#E3F2FD] px-3 py-2 text-xs font-semibold text-[#0D47A1] hover:bg-[#90CAF9]" @click="openDialog('edit', permission)"><Pencil class="h-3.5 w-3.5" /> Edit</button>
                                        <button type="button" :aria-label="`Delete ${permission.name}`" class="inline-flex items-center gap-1.5 rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100" @click="openDialog('delete', permission)"><Trash2 class="h-3.5 w-3.5" /> Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="permissions.data.length === 0">
                                <td colspan="6" class="px-5 py-16 text-center">
                                    <ShieldCheck class="mx-auto mb-3 h-10 w-10 text-[#2196F3]" />
                                    <p class="font-medium text-gray-700">No permissions found</p>
                                    <p class="mt-1 text-gray-500">{{ filters.search ? 'Try another search.' : 'Permissions will appear here once created.' }}</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-col gap-3 border-t border-[#E3F2FD] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-gray-500">Showing {{ permissions.from ?? 0 }} to {{ permissions.to ?? 0 }} of {{ permissions.total }} permissions</p>
                    <nav aria-label="Permission pagination" class="flex items-center gap-3 text-sm text-[#0D47A1]">
                        <button v-if="permissions.prev_page_url" type="button" :disabled="loading" class="rounded-lg border border-[#90CAF9] p-2 hover:bg-[#E3F2FD] disabled:opacity-50" aria-label="Previous page" @click="loadPermissions(permissions.current_page - 1)"><ChevronLeft class="h-4 w-4" /></button>
                        <button v-else disabled aria-label="Previous page" class="rounded-lg border border-gray-200 p-2 text-gray-300"><ChevronLeft class="h-4 w-4" /></button>
                        <span>Page {{ permissions.current_page }} of {{ permissions.last_page }}</span>
                        <button v-if="permissions.next_page_url" type="button" :disabled="loading" class="rounded-lg border border-[#90CAF9] p-2 hover:bg-[#E3F2FD] disabled:opacity-50" aria-label="Next page" @click="loadPermissions(permissions.current_page + 1)"><ChevronRight class="h-4 w-4" /></button>
                        <button v-else disabled aria-label="Next page" class="rounded-lg border border-gray-200 p-2 text-gray-300"><ChevronRight class="h-4 w-4" /></button>
                    </nav>
                </div>
            </div>
        </div>
        <dialog ref="dialog" aria-labelledby="permission-dialog-title" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-xl border border-blue-100 bg-white p-6 shadow-xl backdrop:bg-black/50" @cancel.prevent="closeDialog">
            <form @submit.prevent="submit">
                <p v-if="form.error" role="alert" class="mb-3 text-sm text-red-600">{{ form.error }}</p>
                <div class="mb-4 flex items-center justify-between gap-4">
                    <h2 id="permission-dialog-title" class="text-lg font-bold text-[#0D47A1]">{{ mode === 'delete' ? 'Delete Permission' : 'Edit Permission' }}</h2>
                    <button type="button" aria-label="Close dialog" :disabled="form.processing" class="rounded-lg p-2 hover:bg-blue-50 disabled:opacity-50" @click="closeDialog"><X class="h-5 w-5" /></button>
                </div>
                <p v-if="mode === 'delete'" class="text-sm text-gray-600">Delete “{{ selected?.name }}”? This cannot be undone. Assigned permissions must be removed from their roles and users first.</p>
                <div v-else>
                    <label for="permission-name" class="mb-2 block text-sm font-medium text-gray-700">Permission name</label>
                    <input id="permission-name" ref="nameInput" v-model="form.name" required maxlength="255" :disabled="form.processing" :aria-invalid="!!form.errors.name" aria-describedby="permission-name-help permission-name-error" class="w-full rounded-lg border border-[#90CAF9] px-3 py-2.5 text-sm focus:outline-2 focus:outline-[#2196F3]" placeholder="e.g. franchises.view" />
                    <p id="permission-name-help" class="mt-2 text-xs text-gray-500">Use a unique name. This permission uses the web guard.</p>
                    <p id="permission-name-error" class="mt-2 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>
                <p v-if="form.errors.permission" role="alert" class="mt-3 text-sm text-red-600">{{ form.errors.permission }}</p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" :disabled="form.processing" class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600 disabled:opacity-50" @click="closeDialog">Cancel</button>
                    <button type="submit" :disabled="form.processing" class="rounded-lg px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :class="mode === 'delete' ? 'bg-red-600 hover:bg-red-700' : 'bg-[#2196F3] hover:bg-[#0D47A1]'">{{ form.processing ? 'Saving...' : mode === 'delete' ? 'Delete Permission' : 'Save Permission' }}</button>
                </div>
            </form>
        </dialog>
        <CreatePermission  v-model:open="createOpen" @created="permissionCreated" />
    </AuthenticatedLayout>
</template>

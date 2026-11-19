<script setup>
import { inject, onBeforeUnmount, ref, watch } from 'vue'
import { ChevronLeft, ChevronRight, Search, X } from 'lucide-vue-next'
import http from '@/lib/http'

const open = defineModel('open', { type: Boolean, default: false })
const props = defineProps({ permission: { type: Object, default: null } })
const emit = defineEmits(['updated'])
const route = inject('route')
const dialog = ref(null)
const search = ref('')
const users = ref(null)
const loading = ref(false)
const savingId = ref(null)
const loadError = ref('')
const saveError = ref('')
const notice = ref('')
let request
let timer

async function loadUsers(page = 1) {
    clearTimeout(timer)
    request?.abort()
    const current = new AbortController()
    request = current
    loading.value = true
    loadError.value = ''
    try {
        const { data } = await http.get(route('permissions.users.index', props.permission.id), {
            params: { search: search.value, page }, signal: current.signal,
        })
        if (current.signal.aborted) return
        if (page > data.users.last_page) return await loadUsers(data.users.last_page)
        users.value = data.users
    } catch (error) {
        if (!current.signal.aborted) loadError.value = error.response?.status === 403
            ? 'You no longer have access to manage this permission.' : 'Unable to load users.'
    } finally {
        if (request === current) loading.value = false
    }
}

watch(open, isOpen => {
    if (isOpen) {
        search.value = ''
        users.value = null
        saveError.value = ''
        notice.value = ''
        dialog.value.showModal()
        loadUsers()
    } else {
        clearTimeout(timer)
        request?.abort()
        dialog.value.close()
    }
}, { flush: 'post' })

function searchUsers() {
    request?.abort()
    clearTimeout(timer)
    loading.value = true
    timer = setTimeout(loadUsers, 250)
}

function close() {
    if (savingId.value === null) open.value = false
}

async function changeGrant(user) {
    if (loading.value || savingId.value !== null) return
    const granted = !user.has_direct_permission
    const permissionId = props.permission.id
    savingId.value = user.id
    saveError.value = ''
    notice.value = ''
    try {
        const { data } = await http.put(route('permissions.users.update', [permissionId, user.id]), { granted })
        user.has_direct_permission = granted
        notice.value = `${data.message} ${user.name} (@${user.username})`
        emit('updated', permissionId, data.users_count)
    } catch (error) {
        saveError.value = error.response?.status === 403 ? 'You no longer have access to manage this permission.' : 'Unable to update this user. Please try again.'
    } finally {
        savingId.value = null
    }
}

onBeforeUnmount(() => { clearTimeout(timer); request?.abort() })
</script>

<template>
    <Teleport to="body">
        <dialog ref="dialog" aria-labelledby="permission-users-title" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-2xl overflow-y-auto rounded-xl border border-blue-200 bg-white shadow-xl backdrop:bg-black/50" @cancel.prevent="close">
            <div class="flex items-start justify-between gap-3 border-b border-blue-100 p-5">
                <div class="min-w-0">
                    <h2 id="permission-users-title" class="text-lg font-bold text-[#0D47A1]">User assignments</h2>
                    <p class="mt-1 break-words text-sm font-semibold text-slate-700">{{ permission?.name }}</p>
                </div>
                <button type="button" aria-label="Close user assignments" :disabled="savingId !== null" class="shrink-0 rounded-lg p-2 hover:bg-blue-50 disabled:opacity-50" @click="close"><X aria-hidden="true" class="h-5 w-5" /></button>
            </div>
            <div class="space-y-3 p-5">
                <p class="text-sm text-slate-600">Direct permissions apply to a user in addition to permissions from their roles.</p>
                <div class="relative">
                    <Search aria-hidden="true" class="absolute left-3 top-3 h-4 w-4 text-blue-600" />
                    <input v-model="search" type="search" autofocus maxlength="255" aria-label="Search users by name or username" placeholder="Search name or username..." :disabled="savingId !== null" class="w-full rounded-lg border border-blue-300 py-2.5 pl-9 pr-3 text-sm" @input="searchUsers" />
                </div>
                <p v-if="loadError" role="alert" class="text-sm text-red-700">{{ loadError }} <button type="button" class="underline" @click="loadUsers()">Retry</button></p>
                <p v-if="saveError" role="alert" class="text-sm text-red-700">{{ saveError }}</p>
                <p v-if="notice" role="status" class="rounded-lg bg-green-50 px-3 py-2 text-sm text-green-800">{{ notice }}</p>
                <p v-if="loading" role="status" class="text-sm text-slate-600">Loading users...</p>
                <div v-if="users?.data.length" class="overflow-x-auto rounded-lg border border-blue-100" :aria-busy="loading">
                    <table class="w-full min-w-[320px] text-left text-sm">
                        <thead class="bg-blue-50 text-[#0D47A1]"><tr><th scope="col" class="px-3 py-2">User</th><th scope="col" class="px-3 py-2">Direct access</th><th scope="col" class="px-3 py-2 text-right">Action</th></tr></thead>
                        <tbody class="divide-y divide-blue-100">
                            <tr v-for="user in users.data" :key="user.id">
                                <th scope="row" class="px-3 py-2 font-medium text-slate-800">{{ user.name }} <span class="block text-xs font-normal text-slate-600">@{{ user.username }}</span></th>
                                <td class="px-3 py-2"><span v-if="user.has_direct_permission" class="rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800">Granted</span><span v-else class="text-xs text-slate-500">None</span></td>
                                <td class="px-3 py-2 text-right">
                                    <button type="button" :disabled="loading || savingId !== null" :aria-label="`${user.has_direct_permission ? 'Remove direct permission from' : 'Grant direct permission to'} ${user.username}`" class="rounded-lg px-3 py-1.5 text-xs font-semibold disabled:opacity-50" :class="user.has_direct_permission ? 'bg-red-50 text-red-700 hover:bg-red-100' : 'bg-blue-100 text-[#0D47A1] hover:bg-blue-200'" @click="changeGrant(user)">
                                        {{ savingId === user.id ? 'Saving...' : user.has_direct_permission ? 'Remove' : 'Grant' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else-if="users && !loading && !loadError" class="py-4 text-center text-sm text-slate-600">No users found.</p>
                <nav v-if="users?.last_page > 1" aria-label="User pagination" class="flex items-center justify-end gap-2 text-sm text-[#0D47A1]">
                    <button type="button" :disabled="loading || savingId !== null || !users.prev_page_url" aria-label="Previous page" class="rounded-lg border border-blue-200 p-1.5 disabled:opacity-50" @click="loadUsers(users.current_page - 1)"><ChevronLeft aria-hidden="true" class="h-4 w-4" /></button>
                    <span>Page {{ users.current_page }} of {{ users.last_page }}</span>
                    <button type="button" :disabled="loading || savingId !== null || !users.next_page_url" aria-label="Next page" class="rounded-lg border border-blue-200 p-1.5 disabled:opacity-50" @click="loadUsers(users.current_page + 1)"><ChevronRight aria-hidden="true" class="h-4 w-4" /></button>
                </nav>
            </div>
        </dialog>
    </Teleport>
</template>

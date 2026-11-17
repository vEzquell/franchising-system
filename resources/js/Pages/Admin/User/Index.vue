
<script setup>
import { ref, computed, inject, onBeforeUnmount, onMounted, watch } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'
import http from '@/lib/http'
import CreateUser from './Create.vue'
import EditUser from './Edit.vue'
import {
    Plus,
    Search,
    Pencil,
    Users,
    ChevronLeft,
    ChevronRight,
    UserRound
} from 'lucide-vue-next'

// Props from Laravel UserController
const props = defineProps({
    roles: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: '' }) },
    users: {
        type: Object,
        default: () => ({
            data: [],
            current_page: 1,
            last_page: 1,
            total: 0,
            from: null,
            to: null
        })
    }
})

// Search
const route = inject('route')
const search = ref(props.filters.search)
const users = ref(props.users)
const createOpen = ref(false)
const editOpen = ref(false)
const selectedUser = ref(null)
const notice = ref('')
const loadError = ref('')
const loading = ref(false)
let searchTimer
let listRequest

onMounted(() => {
    if (new URLSearchParams(window.location.search).get('create') === '1') createOpen.value = true
})
watch(() => props.users, (value) => { users.value = value })
watch(() => props.filters.search, (value) => { if (search.value !== value) search.value = value })
watch(search, () => {
    clearTimeout(searchTimer)
    listRequest?.abort()
    searchTimer = setTimeout(() => loadUsers(), 300)
})
onBeforeUnmount(() => { clearTimeout(searchTimer); listRequest?.abort() })

async function loadUsers(page = 1) {
    clearTimeout(searchTimer)
    listRequest?.abort()
    const request = new AbortController()
    listRequest = request
    loading.value = true
    loadError.value = ''
    try {
        const { data } = await http.get(route('users'), { params: { search: search.value, page }, signal: request.signal })
        if (!request.signal.aborted) users.value = data.users
    } catch (error) {
        if (!request.signal.aborted) loadError.value = 'Unable to load users. Please try again.'
    } finally {
        if (listRequest === request) loading.value = false
    }
}

function userCreated() {
    notice.value = 'User created successfully.'
    if (search.value) search.value = ''
    else loadUsers()
}

function editUser(user) {
    selectedUser.value = user
    editOpen.value = true
}

function userUpdated() {
    notice.value = 'User updated successfully.'
    loadUsers(users.value.current_page)
}

// Results from the server, including search across all pages
const filteredUsers = computed(() => users.value.data)

// Display role
const getRole = (user) => {
    return user.roles?.map(role => role.name).join(', ')
        || 'No Role'
}

// Role badge colors
const roleClass = (user) => {
    const role = user.roles?.[0]?.name

    switch (role) {
        case 'superadmin':
            return 'bg-[#0D47A1] text-white'
        case 'admin':
            return 'bg-[#2196F3] text-white'
        case 'evaluator':
            return 'bg-[#E3F2FD] text-[#0D47A1]'
        default:
            return 'bg-gray-100 text-gray-600'
    }
}

</script>

<template>
    <!-- Main Container -->
    <Head title="User Management" />
    <AuthenticatedLayout>
        <div class="min-h-full bg-[#E3F2FD]/30 p-4 md:p-6">
            <p v-if="notice" role="status" class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ notice }}</p>
            <p v-if="loadError" role="alert" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ loadError }} <button type="button" class="underline" @click="loadUsers(users.current_page)">Retry</button></p>
            <p v-if="loading" role="status" class="mb-3 text-sm text-gray-500">Loading users...</p>

            <div class="overflow-hidden rounded-xl border border-[#90CAF9]/50 bg-white shadow-sm">

                <!-- Header -->
                <div class="flex flex-col gap-4 border-b border-[#E3F2FD] p-5 md:flex-row md:items-center md:justify-between">

                    <!-- Left: Title -->
                    <div>
                        <div class="flex items-center gap-2">
                            <Users class="h-6 w-6 text-[#0D47A1]" />

                            <h1 class="text-xl font-bold text-[#0D47A1]">
                                User Management
                            </h1>
                        </div>

                        <p class="mt-1 text-sm text-gray-500">
                            Manage system users, roles and account access.
                        </p>
                    </div>

                    <!-- Right: Search and Add -->
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">

                        <!-- Search -->
                        <div class="relative">
                            <Search
                                class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#2196F3]"
                            />

                            <input
                                v-model="search"
                                aria-label="Search users"
                                maxlength="255"
                                type="text"
                                placeholder="Search users..."
                                class="w-full rounded-lg border border-[#90CAF9] bg-white py-2.5 pl-10 pr-4 text-sm text-gray-700 outline-none transition focus:border-[#2196F3] focus:ring-2 focus:ring-[#90CAF9]/30 sm:w-64"
                            />
                        </div>

                        <!-- Add User -->
                        <button
                            
                            type="button"
                            @click="createOpen = true"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#2196F3] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#0D47A1]"
                        >
                            <Plus class="h-4 w-4" />
                            Add User
                        </button>
                    </div>
                </div>

                <!-- Body -->
                <div class="overflow-x-auto" :aria-busy="loading">
                    <table class="w-full min-w-[750px] text-left text-sm">

                        <!-- Table Header -->
                        <thead class="bg-[#E3F2FD] text-[#0D47A1]">
                            <tr>
                                <th class="px-5 py-4 font-semibold">
                                    #
                                </th>

                                <th class="px-5 py-4 font-semibold">
                                    User
                                </th>

                                <th class="px-5 py-4 font-semibold">
                                    Username
                                </th>

                                <th class="px-5 py-4 font-semibold">
                                    Role
                                </th>

                                <th class="px-5 py-4 font-semibold">
                                    Status
                                </th>

                                <th class="px-5 py-4 font-semibold">
                                    Last Login
                                </th>

                                <th class="px-5 py-4 text-center font-semibold">
                                    Actions
                                </th>
                            </tr>
                        </thead>

                        <!-- Table Body -->
                        <tbody class="divide-y divide-gray-100">

                            <tr
                                v-for="(user, index) in filteredUsers"
                                :key="user.id"
                                class="transition hover:bg-[#E3F2FD]/40"
                            >
                                <!-- Number -->
                                <td class="px-5 py-4 text-gray-500">
                                    {{ (users.from ?? 1) + index }}
                                </td>

                                <!-- Name -->
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#E3F2FD]">
                                            <UserRound class="h-5 w-5 text-[#2196F3]" />
                                        </div>

                                        <span class="font-medium text-gray-800">
                                            {{ user.name }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Username -->
                                <td class="px-5 py-4 text-gray-600">
                                    {{ user.username }}
                                </td>

                                <!-- Role -->
                                <td class="px-5 py-4">
                                    <span
                                        :class="roleClass(user)"
                                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize"
                                    >
                                        {{ getRole(user) }}
                                    </span>
                                </td>

                                <!-- Status -->
                                <td class="px-5 py-4">
                                    <span
                                        :class="user.status === 'active'
                                            ? 'bg-green-100 text-green-700'
                                            : 'bg-red-100 text-red-700'"
                                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold capitalize"
                                    >
                                        <span
                                            :class="user.status === 'active'
                                                ? 'bg-green-500'
                                                : 'bg-red-500'"
                                            class="h-1.5 w-1.5 rounded-full"
                                        ></span>

                                        {{ user.status }}
                                    </span>
                                </td>

                                <!-- Last Login -->
                                <td class="px-5 py-4 text-gray-500">
                                    {{
                                        user.last_login_at
                                            ? new Date(user.last_login_at).toLocaleString()
                                            : 'Never'
                                    }}
                                </td>

                                <!-- Actions -->
                                <td class="px-5 py-4">
                                    <div class="flex justify-center">
                                        <button
                                            
                                            type="button"
                                            :aria-label="`Edit ${user.name}`"
                                            @click="editUser(user)"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-[#E3F2FD] px-3 py-2 text-xs font-semibold text-[#0D47A1] transition hover:bg-[#90CAF9]"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                            Edit
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-if="filteredUsers.length === 0">
                                <td colspan="7" class="px-5 py-16 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="rounded-full bg-[#E3F2FD] p-4">
                                            <Users class="h-8 w-8 text-[#2196F3]" />
                                        </div>

                                        <p class="font-medium text-gray-700">
                                            No users found
                                        </p>

                                        <p class="text-sm text-gray-400">
                                            Try another search or add a new user.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer / Pagination -->
                <div class="flex flex-col gap-3 border-t border-[#E3F2FD] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">

                    <!-- Results Info -->
                    <p class="text-sm text-gray-500">
                        Showing
                        <span class="font-semibold text-[#0D47A1]">
                            {{ users.from ?? 0 }}
                        </span>
                        to
                        <span class="font-semibold text-[#0D47A1]">
                            {{ users.to ?? 0 }}
                        </span>
                        of
                        <span class="font-semibold text-[#0D47A1]">
                            {{ users.total }}
                        </span>
                        users
                    </p>

                    <!-- Pagination Buttons -->
                    <div class="flex items-center gap-2">

                        <!-- Previous -->
                        <button
                            v-if="users.current_page > 1"
                            type="button"
                            :disabled="loading"
                            aria-label="Previous page"
                            @click="loadUsers(users.current_page - 1)"
                            class="rounded-lg border border-[#90CAF9] p-2 text-[#0D47A1] transition hover:bg-[#E3F2FD]"
                        >
                            <ChevronLeft class="h-4 w-4" />
                        </button>

                        <span
                            v-else
                            class="rounded-lg border border-gray-200 p-2 text-gray-300"
                        >
                            <ChevronLeft class="h-4 w-4" />
                        </span>

                        <!-- Current Page -->
                        <span class="rounded-lg bg-[#2196F3] px-3.5 py-2 text-sm font-semibold text-white">
                            {{ users.current_page }}
                        </span>

                        <span class="text-sm text-gray-400">
                            of {{ users.last_page }}
                        </span>

                        <!-- Next -->
                        <button
                            v-if="users.current_page < users.last_page"
                            type="button"
                            :disabled="loading"
                            aria-label="Next page"
                            @click="loadUsers(users.current_page + 1)"
                            class="rounded-lg border border-[#90CAF9] p-2 text-[#0D47A1] transition hover:bg-[#E3F2FD]"
                        >
                            <ChevronRight class="h-4 w-4" />
                        </button>

                        <span
                            v-else
                            class="rounded-lg border border-gray-200 p-2 text-gray-300"
                        >
                            <ChevronRight class="h-4 w-4" />
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <CreateUser  v-model:open="createOpen" :roles="roles" @created="userCreated" />
        <EditUser  v-model:open="editOpen" :user="selectedUser" :roles="roles" @updated="userUpdated" />
    </AuthenticatedLayout>
</template>

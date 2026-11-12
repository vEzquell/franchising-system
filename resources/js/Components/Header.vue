<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { ChevronDown, LogOut, Menu, Settings, ShieldCheck } from 'lucide-vue-next'

defineEmits(['toggle-sidebar'])
const page = usePage()
const dropdownOpen = ref(false)
const account = ref(null)
const trigger = ref(null)
const user = computed(() => page.props.auth?.user)
const name = computed(() => user.value?.name || user.value?.username || 'My account')
const initials = computed(() => name.value.trim().split(/\s+/).slice(0, 2).map(part => part[0]).join('').toUpperCase())
const title = computed(() => page.component.startsWith('Admin/User/') ? 'User Management' : page.component.includes('Dashboard') ? 'Dashboard' : 'Workspace')

function closeOutside(event) {
    if (!account.value?.contains(event.target)) dropdownOpen.value = false
}
function closeOnEscape(event) {
    if (event.key === 'Escape' && dropdownOpen.value) {
        dropdownOpen.value = false
        trigger.value?.focus()
    }
}
function closeOnFocusOut(event) {
    if (!event.currentTarget.contains(event.relatedTarget)) dropdownOpen.value = false
}
watch(() => page.url, () => { dropdownOpen.value = false })
onMounted(() => {
    document.addEventListener('click', closeOutside)
    document.addEventListener('keydown', closeOnEscape)
})
onBeforeUnmount(() => {
    document.removeEventListener('click', closeOutside)
    document.removeEventListener('keydown', closeOnEscape)
})
</script>

<template>
    <header class="relative z-30 flex h-[76px] shrink-0 items-center justify-between gap-4 border-b border-blue-100 bg-white px-4 md:px-6">
        <div class="flex min-w-0 items-center gap-3">
            <button type="button" aria-label="Open navigation" class="rounded-xl p-2 text-[#0D47A1] hover:bg-[#E3F2FD] focus-visible:outline-2 focus-visible:outline-[#2196F3] lg:hidden" @click="$emit('toggle-sidebar')">
                <Menu class="h-5 w-5" />
            </button>
            <div class="min-w-0">
                <p class="mb-0.5 text-[10px] font-semibold uppercase tracking-[0.18em] text-blue-400">Franchising Office</p>
                <h2 class="truncate text-lg font-bold tracking-tight text-[#0D47A1]">{{ title }}</h2>
            </div>
        </div>
        <div class="flex items-center gap-5">
            <div class="hidden items-center gap-2 border-r border-blue-100 pr-5 text-xs font-medium text-blue-500 md:flex">
                <ShieldCheck class="h-4 w-4" /> Management Portal
            </div>
            <div ref="account" class="relative" @focusout="closeOnFocusOut">
                <button
                    id="account-toggle" ref="trigger" type="button" aria-label="Account options"
                    aria-controls="account-dropdown" :aria-expanded="dropdownOpen"
                    class="flex items-center gap-3 rounded-2xl p-1.5 text-left transition hover:bg-[#E3F2FD] focus-visible:outline-2 focus-visible:outline-[#2196F3]"
                    :class="{ 'bg-[#E3F2FD]': dropdownOpen }" @click="dropdownOpen = !dropdownOpen"
                >
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-[#2196F3] to-[#0D47A1] text-sm font-bold text-white shadow-sm">{{ initials }}</span>
                    <span class="hidden sm:block">
                        <span class="block max-w-36 truncate text-sm font-semibold text-[#0D47A1]">{{ name }}</span>
                        <span class="block text-[11px] text-blue-400">Account options</span>
                    </span>
                    <ChevronDown class="mr-1 h-4 w-4 text-blue-400 transition-transform" :class="{ 'rotate-180': dropdownOpen }" />
                </button>
                <Transition enter-active-class="transition duration-150 ease-out" enter-from-class="translate-y-1 scale-95 opacity-0" enter-to-class="translate-y-0 scale-100 opacity-100" leave-active-class="transition duration-100 ease-in" leave-to-class="translate-y-1 scale-95 opacity-0">
                    <div v-if="dropdownOpen" id="account-dropdown" aria-labelledby="account-toggle" class="absolute right-0 top-full mt-3 w-64 origin-top-right overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-xl shadow-blue-900/10">
                        <div class="border-b border-blue-100 bg-[#E3F2FD]/60 px-4 py-4">
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-blue-400">Signed in as</p>
                            <p class="mt-1 truncate text-sm font-bold text-[#0D47A1]">{{ name }}</p>
                            <p v-if="user?.username" class="mt-0.5 truncate text-xs text-blue-500">@{{ user.username }}</p>
                        </div>
                        <div class="space-y-1 p-2">
                            <Link v-if="page.props.navigation?.settings" :href="page.props.navigation.settings" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-[#0D47A1] hover:bg-[#E3F2FD]" @click="dropdownOpen = false">
                                <Settings class="h-4 w-4 text-[#2196F3]" /> Settings
                            </Link>
                            <div v-else class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-400" aria-disabled="true">
                                <Settings class="h-4 w-4" /> Settings
                                <span class="ml-auto rounded-md bg-blue-50 px-2 py-1 text-[10px] font-medium text-blue-400">Coming soon</span>
                            </div>
                            <Link v-if="page.props.navigation?.logout" :href="page.props.navigation.logout" method="post" as="button" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-red-500 hover:bg-red-50" @click="dropdownOpen = false">
                                <LogOut class="h-4 w-4" /> Sign out
                            </Link>
                        </div>
                    </div>
                </Transition>
            </div>
        </div>
    </header>
</template>

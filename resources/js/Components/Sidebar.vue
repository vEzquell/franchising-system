<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

import {
    LayoutDashboard,
    FileText,
    Users,
    Settings,
    LogOut,
    X,
    ChevronRight
} from 'lucide-vue-next'

// Props
defineProps({
    open: {
        type: Boolean,
        default: false
    }
})

// Emits
const emit = defineEmits(['close'])

// Current Page
const page = usePage()

// Navigation Menus
const menus = [
    {
        name: 'Dashboard',
        href: '/dashboard',
        icon: LayoutDashboard
    },
    {
        name: 'Franchises',
        href: '/franchises',
        icon: FileText
    },
    {
        name: 'User Management',
        href: '/users',
        icon: Users
    },
    {
        name: 'Settings',
        href: '/settings',
        icon: Settings
    }
]

// Check Active Route
const isActive = (href) => {
    return page.url === href ||
        (href !== '/dashboard' && page.url.startsWith(href + '/'))
}

// Current User
const user = computed(() => page.props.auth?.user)

// Close Sidebar
const closeSidebar = () => {
    emit('close')
}
</script>

<template>
    <!-- Mobile Dark Overlay -->
    <Transition
        enter-active-class="transition-opacity duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-300"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="open"
            class="fixed inset-0 bg-black/50 z-40 lg:hidden backdrop-blur-[2px]"
            @click="closeSidebar"
        ></div>
    </Transition>

    <!-- Sidebar -->
    <aside
        class="
            fixed lg:static
            inset-y-0 left-0
            z-50
            w-72 lg:w-64
            h-dvh lg:h-screen
            bg-[#0D47A1]
            text-white
            flex flex-col
            shrink-0
            shadow-2xl lg:shadow-none
            transition-transform duration-300 ease-in-out
            lg:translate-x-0
        "
        :class="open ? 'translate-x-0' : '-translate-x-full'"
    >
        <!-- Sidebar Header -->
        <div class="h-[76px] flex items-center justify-between px-5 border-b border-white/10 shrink-0">
            <!-- Logo + Title -->
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 bg-white rounded-xl p-1 flex items-center justify-center shrink-0 shadow-sm">
                    <img
                        :src="`${$url('/assets/sc_logo.png')}`"
                        alt="City Seal"
                        class="w-full h-full object-contain"
                    />
                </div>

                <div class="min-w-0">
                    <h1 class="font-bold text-[13px] leading-tight tracking-tight">
                        Franchising Office
                    </h1>
                    <p class="text-[10px] text-[#90CAF9] mt-0.5 font-medium tracking-wide">
                        Management System
                    </p>
                </div>
            </div>

            <!-- Mobile Close Button -->
            <button
                type="button"
                @click="closeSidebar"
                aria-label="Close sidebar"
                class="lg:hidden p-2 rounded-lg text-blue-100 hover:bg-white/10 hover:text-white transition-colors"
            >
                <X class="w-5 h-5" />
            </button>
        </div>

        <!-- Navigation -->
        <nav
            aria-label="Main navigation"
            class="flex-1 overflow-y-auto min-h-0 px-3 py-5"
        >
            <!-- Section Label -->
            <p class="px-3 mb-3 text-[10px] font-semibold uppercase tracking-[0.18em] text-[#90CAF9]/80">
                Main Menu
            </p>

            <!-- Menu Items -->
            <div class="space-y-1">
                <Link
                    v-for="menu in menus"
                    :key="menu.name"
                    :href="menu.href"
                    @click="closeSidebar"
                    :aria-current="isActive(menu.href) ? 'page' : undefined"
                    class="
                        group relative
                        flex items-center gap-3
                        px-3 py-2.5
                        rounded-xl
                        text-sm font-medium
                        transition-all duration-200
                    "
                    :class="
                        isActive(menu.href)
                            ? 'bg-[#2196F3] text-white shadow-lg shadow-blue-900/30'
                            : 'text-blue-100 hover:bg-white/10 hover:text-white'
                    "
                >
                    <!-- Left accent bar for active state -->
                    <span
                        v-if="isActive(menu.href)"
                        class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-white rounded-r-full"
                    ></span>

                    <component
                        :is="menu.icon"
                        class="w-[18px] h-[18px] shrink-0"
                        :class="isActive(menu.href) ? 'text-white' : 'text-[#90CAF9] group-hover:text-white'"
                    />

                    <span class="flex-1 truncate">
                        {{ menu.name }}
                    </span>

                    <ChevronRight
                        v-if="isActive(menu.href)"
                        class="w-4 h-4 text-white/80"
                    />
                </Link>
            </div>
        </nav>

        <!-- Bottom Section -->
        <div class="shrink-0 border-t border-white/10 px-3 pt-4 pb-5 space-y-3">
            <!-- Logout -->
            <Link
                href="/logout"
                method="post"
                as="button"
                class="
                    w-full
                    flex items-center justify-center gap-2.5
                    px-4 py-2.5
                    rounded-xl
                    bg-red-500/10
                    border border-red-400/20
                    text-red-200
                    hover:bg-red-500 hover:text-white hover:border-red-500
                    transition-all duration-200
                    text-sm font-medium
                "
            >
                <LogOut class="w-4.5 h-4.5" />
                <span>Logout</span>
            </Link>

            <!-- Copyright -->
            <p class="text-center text-[10px] text-[#90CAF9]/70">
                © {{ new Date().getFullYear() }} Franchising Office
            </p>
        </div>
    </aside>
</template>
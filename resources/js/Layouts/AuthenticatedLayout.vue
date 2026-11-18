<script setup>
import { ref } from 'vue'
import Sidebar from '@/Components/Sidebar.vue'
import Header from '@/Components/Header.vue'


const sidebarOpen = ref(false)
</script>

<template>
    <div class="h-dvh overflow-hidden flex bg-[#E3F2FD]">

        <Sidebar
            :open="sidebarOpen"
            @close="sidebarOpen = false"
        />

        <div class="flex-1 min-h-0 min-w-0 flex flex-col">

            <Header
                @toggle-sidebar="sidebarOpen = !sidebarOpen"
            />

            <main class="flex-1 min-h-0 overflow-y-auto p-4 md:p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
<script setup>
import { ref } from 'vue'
import Sidebar from '@/Components/Sidebar.vue'
import Header from '@/Components/Header.vue'
import Footer from '@/Components/Footer.vue'

const sidebarOpen = ref(false)
</script>

<template>
    <div class="min-h-screen flex bg-[#E3F2FD]">

        <Sidebar
            :open="sidebarOpen"
            @close="sidebarOpen = false"
        />

        <div class="flex-1 min-w-0 flex flex-col">

            <Header
                @toggle-sidebar="sidebarOpen = !sidebarOpen"
            />

            <main class="flex-1 p-4 md:p-6">
                <slot />
            </main>

            <Footer />

        </div>
    </div>
</template>
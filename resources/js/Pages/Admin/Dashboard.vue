
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'
</script>

<template>
    <Head title="Admin Dashboard" />

    <AuthenticatedLayout>
        <div>
            <h1 class="text-2xl font-bold text-[#0D47A1]">
                Admin Dashboard
            </h1>

            <p class="text-gray-500 mt-2">
                Welcome to the Franchising Management System.
            </p>
        </div>
    </AuthenticatedLayout>
</template>


<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import {
    User,
    Lock,
    Eye,
    EyeOff,
    LogIn,
    CircleAlert
} from 'lucide-vue-next'

// Inertia Form
const form = useForm({
    username: '',
    password: '',
    remember: false,
})

const showPassword = ref(false)

// Submit Login
const handleLogin = () => {
    form.post('/login', {
        preserveScroll: true,

        onFinish: () => {
            form.reset('password')
        },
    })
}
</script>

<template>
    <div class="min-h-screen w-full flex items-center justify-center bg-[#E3F2FD] p-4 sm:p-6 lg:p-8">

        <!-- Main Card -->
        <div class="w-full max-w-md bg-white/95 backdrop-blur-md rounded-2xl shadow-xl border border-[#90CAF9]/40 p-8 sm:p-10">

            <!-- Header -->
            <div class="flex items-center gap-4 mb-8 pb-6 border-b border-[#90CAF9]/30">

                <div class="shrink-0 w-25 h-25 rounded-2xl bg-white p-1.5 shadow-md border border-[#90CAF9]/40 flex items-center justify-center">
                    <img
                        :src="'/assets/sc_logo.png'"
                        alt="City Seal Logo"
                        class="w-full h-full object-contain"
                    />
                </div>

                <div class="flex flex-col text-left">
                    <h1 class="text-[2em] font-bold text-[#0D47A1] leading-tight tracking-tight">
                        Franchising Office
                    </h1>

                    <p class="text-[11px] font-semibold uppercase tracking-wider text-[#2196F3] mt-0.5">
                        Management Information System
                    </p>
                </div>
            </div>

            <!-- Error Message -->
            <div
                v-if="form.errors.username || form.errors.password"
                role="alert"
                class="mb-5 flex items-start gap-3 bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-xl"
            >
                <CircleAlert class="w-5 h-5 shrink-0 mt-0.5" />

                <div>
                    <p class="text-sm font-semibold">
                        Login Failed
                    </p>

                    <p class="text-xs mt-1">
                        {{ form.errors.username || form.errors.password }}
                    </p>
                </div>
            </div>

            <!-- Login Form -->
            <form @submit.prevent="handleLogin" class="space-y-5">

                <!-- Username -->
                <div>
                    <label for="username" class="block text-sm font-medium text-[#0D47A1] mb-1.5">
                        Username
                    </label>

                    <div class="relative flex items-center">
                        <span class="absolute left-3.5 text-[#2196F3]">
                            <User class="w-5 h-5" />
                        </span>

                        <input
                            id="username"
                            v-model="form.username"
                            type="text"
                            autocomplete="username"
                            required
                            placeholder="Enter your username"
                            :class="form.errors.username ? 'border-red-500' : 'border-[#90CAF9]'"
                            class="w-full pl-11 pr-4 py-2.5 bg-[#E3F2FD]/30 border rounded-xl text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#2196F3] transition-all text-sm"
                        />
                    </div>
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-[#0D47A1] mb-1.5">
                        Password
                    </label>

                    <div class="relative flex items-center">
                        <span class="absolute left-3.5 text-[#2196F3]">
                            <Lock class="w-5 h-5" />
                        </span>

                        <input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="current-password"
                            required
                            placeholder="••••••••"
                            :class="form.errors.password ? 'border-red-500' : 'border-[#90CAF9]'"
                            class="w-full pl-11 pr-11 py-2.5 bg-[#E3F2FD]/30 border rounded-xl text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#2196F3] transition-all text-sm"
                        />

                        <button
                            type="button"
                            :aria-label="showPassword ? 'Hide password' : 'Show password'"
                            @click="showPassword = !showPassword"
                            class="absolute right-3.5 text-[#2196F3]/70 hover:text-[#0D47A1] cursor-pointer"
                        >
                            <Eye v-if="!showPassword" class="w-5 h-5" />
                            <EyeOff v-else class="w-5 h-5" />
                        </button>
                    </div>
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between text-xs pt-1">
                    <label class="flex items-center space-x-2 cursor-pointer select-none">
                        <input
                            v-model="form.remember"
                            type="checkbox"
                            class="w-4 h-4 rounded border-[#90CAF9] text-[#2196F3] focus:ring-[#2196F3]"
                        />
                        <span class="text-slate-600">
                            Remember me
                        </span>
                    </label>
                </div>

                <!-- Login Button -->
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full mt-2 py-3 px-4 bg-[#0D47A1] hover:bg-[#2196F3] text-white font-semibold rounded-xl shadow-md disabled:opacity-60 disabled:cursor-not-allowed transition-all duration-200 text-sm flex items-center justify-center gap-2 cursor-pointer"
                >
                    <LogIn class="w-5 h-5" />

                    {{ form.processing ? 'Signing In...' : 'Login' }}
                </button>
            </form>

            <!-- Footer -->
            <div class="mt-8 text-center pt-2">
                <p class="text-xs text-slate-500">
                    Surigao City Official Municipal Franchising Portal
                </p>
            </div>

        </div>
    </div>
</template>

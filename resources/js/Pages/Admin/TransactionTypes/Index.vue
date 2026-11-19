<script setup>
import { computed, inject, reactive, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { Pencil, Plus, Tags, X } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import http from '@/lib/http'

const props = defineProps({ transactionTypes: { type: Array, required: true }, builtInCodes: { type: Array, required: true } })
const route = inject('route')
const types = ref(props.transactionTypes)
const orderedTypes = computed(() => [...types.value].sort((first, second) => first.name.localeCompare(second.name)))
const dialog = ref(null)
const editingCode = ref(null)
const form = reactive({ name: '', is_active: true })
const errors = ref({})
const saveError = ref('')
const notice = ref('')
const saving = ref(false)

function openForm(type = null) {
    editingCode.value = type?.code ?? null
    Object.assign(form, { name: type?.name ?? '', is_active: type?.is_active ?? true })
    errors.value = {}
    saveError.value = ''
    dialog.value.showModal()
}

function closeForm() {
    if (!saving.value) dialog.value.close()
}

async function save() {
    if (saving.value) return
    saving.value = true
    errors.value = {}
    saveError.value = ''
    try {
        const url = editingCode.value ? route('transaction-types.update', editingCode.value) : route('transaction-types.store')
        const { data } = await http.post(url, { ...form })
        types.value = [...types.value.filter(type => type.code !== data.transactionType.code), data.transactionType]
        notice.value = data.message
        dialog.value.close()
    } catch (error) {
        if (error.response?.status === 422) errors.value = error.response.data.errors ?? {}
        else saveError.value = 'Unable to save the transaction type. Please try again.'
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Transaction Types" />
        <div class="min-h-full p-4 md:p-6">
            <p v-if="notice" role="status" class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ notice }}</p>
            <section aria-labelledby="transaction-types-title" class="overflow-hidden rounded-xl border border-blue-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-4 p-5">
                    <div class="flex items-start gap-3">
                        <Tags aria-hidden="true" class="mt-0.5 h-6 w-6 shrink-0 text-[#0D47A1]" />
                        <div>
                            <h1 id="transaction-types-title" class="text-xl font-bold text-[#0D47A1]">Transaction Types</h1>
                            <p class="mt-1 text-sm text-slate-600">Choose which types staff can use when filing transactions.</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Link :href="route('requirements.index')" class="rounded-lg border border-blue-200 px-3 py-2.5 text-sm font-semibold text-[#0D47A1] hover:bg-blue-50">Manage requirements</Link>
                        <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-[#0D47A1] px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-900" @click="openForm()"><Plus aria-hidden="true" class="h-4 w-4" /> Add type</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[480px] text-left text-sm">
                        <thead class="bg-[#E3F2FD] text-[#0D47A1]">
                            <tr><th scope="col" class="px-5 py-3">Name</th><th scope="col" class="px-5 py-3">Workflow</th><th scope="col" class="px-5 py-3">Availability</th><th scope="col" class="px-5 py-3 text-right">Actions</th></tr>
                        </thead>
                        <tbody class="divide-y divide-blue-100">
                            <tr v-for="type in orderedTypes" :key="type.code" class="hover:bg-blue-50/50">
                                <th scope="row" class="px-5 py-3 font-semibold break-words text-slate-800">{{ type.name }}</th>
                                <td class="px-5 py-3 capitalize text-slate-600">{{ builtInCodes.includes(type.code) ? type.code : 'General' }}</td>
                                <td class="px-5 py-3"><span class="inline-block rounded-full px-2.5 py-1 text-xs font-semibold" :class="type.is_active ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-700'">{{ type.is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="px-5 py-3 text-right"><button type="button" :aria-label="`Edit ${type.name}`" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 px-3 py-1.5 font-medium text-[#0D47A1] hover:bg-blue-100" @click="openForm(type)"><Pencil aria-hidden="true" class="h-3.5 w-3.5" /> Edit</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <dialog ref="dialog" aria-labelledby="transaction-type-form-title" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-md overflow-y-auto rounded-xl border border-blue-200 bg-white shadow-xl backdrop:bg-black/50" @cancel.prevent="closeForm">
            <form class="space-y-4 p-5" @submit.prevent="save">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="transaction-type-form-title" class="text-lg font-bold text-[#0D47A1]">{{ editingCode ? 'Edit transaction type' : 'Add transaction type' }}</h2>
                    <button type="button" :disabled="saving" aria-label="Close transaction type form" class="rounded-lg p-2 hover:bg-blue-50 disabled:opacity-50" @click="closeForm"><X aria-hidden="true" class="h-5 w-5" /></button>
                </div>
                <p v-if="!editingCode" class="text-sm text-slate-600">New types use the General workflow with optional remarks and attachments.</p>
                <p v-if="saveError" role="alert" class="text-sm text-red-700">{{ saveError }}</p>
                <fieldset :disabled="saving" class="space-y-4">
                    <div>
                        <label for="transaction-type-name" class="mb-2 block text-sm font-medium">Name</label>
                        <input id="transaction-type-name" v-model="form.name" required maxlength="120" autofocus aria-describedby="transaction-type-name-error" :aria-invalid="!!errors.name" class="w-full rounded-lg border border-blue-300 px-3 py-2.5 text-sm" />
                        <p id="transaction-type-name-error" role="alert" class="text-sm text-red-700">{{ errors.name?.[0] }}</p>
                    </div>
                    <label class="flex items-center gap-2 rounded-lg bg-blue-50 p-3 text-sm font-medium"><input v-model="form.is_active" type="checkbox" aria-describedby="transaction-type-status-error" class="size-4 accent-[#0D47A1]" /> Available for new transactions</label>
                    <p id="transaction-type-status-error" role="alert" class="text-sm text-red-700">{{ errors.is_active?.[0] }}</p>
                </fieldset>
                <div class="flex justify-end gap-3 border-t border-blue-100 pt-4">
                    <button type="button" :disabled="saving" class="rounded-lg border border-slate-200 px-4 py-2 text-sm disabled:opacity-50" @click="closeForm">Cancel</button>
                    <button type="submit" :disabled="saving" class="rounded-lg bg-[#0D47A1] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900 disabled:opacity-50">{{ saving ? 'Saving...' : 'Save type' }}</button>
                </div>
            </form>
        </dialog>
    </AuthenticatedLayout>
</template>

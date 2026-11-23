<script setup>
import { computed, inject, reactive, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import { ClipboardList, Pencil, Plus, X } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import http from '@/lib/http'

const props = defineProps({
    transactionTypes: { type: Object, required: true },
    requirements: { type: Array, required: true },
})

const route = inject('route')
const requirements = ref(props.requirements)
const selectedType = ref(Object.keys(props.transactionTypes)[0])
const visibleRequirements = computed(() => requirements.value
    .filter(requirement => requirement.transaction_type === selectedType.value)
    .sort((first, second) => first.name.localeCompare(second.name)))
const dialog = ref(null)
const editingId = ref(null)
const form = reactive({ transaction_type: '', name: '', description: '' })
const errors = ref({})
const saveError = ref('')
const saving = ref(false)
const notice = ref('')

function openForm(requirement = null) {
    editingId.value = requirement?.id ?? null
    Object.assign(form, {
        transaction_type: requirement?.transaction_type ?? selectedType.value,
        name: requirement?.name ?? '',
        description: requirement?.description ?? '',
    })
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
        const url = editingId.value ? route('requirements.update', editingId.value) : route('requirements.store')
        const { data } = await http.post(url, { ...form })
        requirements.value = [...requirements.value.filter(requirement => requirement.id !== data.requirement.id), data.requirement]
        selectedType.value = data.requirement.transaction_type
        notice.value = data.message
        dialog.value.close()
    } catch (error) {
        if (error.response?.status === 422) errors.value = error.response.data.errors ?? {}
        else saveError.value = 'Unable to save the requirement. Please try again.'
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Transaction Requirements" />

        <div class="min-h-full p-4 md:p-6">
            <p v-if="notice" role="status" class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ notice }}</p>

            <section aria-labelledby="requirements-title" class="overflow-hidden rounded-xl border border-blue-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-4 p-5">
                    <div class="flex items-start gap-3">
                        <ClipboardList aria-hidden="true" class="mt-0.5 h-6 w-6 shrink-0 text-[#0D47A1]" />
                        <div>
                            <h1 id="requirements-title" class="text-xl font-bold text-[#0D47A1]">Transaction Requirements</h1>
                            <p class="mt-1 text-sm text-slate-600">Manage the document list staff see when filing each transaction type.</p>
                        </div>
                    </div>
                    <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#0D47A1] px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-900" @click="openForm()">
                        <Plus aria-hidden="true" class="h-4 w-4" /> Add requirement
                    </button>
                </div>

                <div role="group" aria-label="Transaction types" class="flex flex-wrap gap-2 border-y border-blue-100 bg-blue-50/60 px-5 py-3">
                    <button v-for="(label, value) in transactionTypes" :key="value" type="button" :aria-pressed="selectedType === value"
                        class="rounded-lg px-3.5 py-2 text-sm font-semibold"
                        :class="selectedType === value ? 'bg-[#0D47A1] text-white' : 'text-[#0D47A1] hover:bg-blue-100'"
                        @click="selectedType = value">{{ label }}</button>
                </div>

                <div v-if="visibleRequirements.length" class="overflow-x-auto">
                    <table class="w-full min-w-[440px] text-left text-sm">
                        <thead class="border-b border-blue-100 bg-[#E3F2FD] text-[#0D47A1]">
                            <tr><th scope="col" class="w-2/5 px-5 py-3">Requirement</th><th scope="col" class="px-5 py-3">Instructions</th><th scope="col" class="px-5 py-3 text-right">Actions</th></tr>
                        </thead>
                        <tbody class="divide-y divide-blue-100">
                            <tr v-for="requirement in visibleRequirements" :key="requirement.id" class="hover:bg-blue-50/50">
                                <th scope="row" class="px-5 py-3 font-semibold break-words text-slate-800">{{ requirement.name }}</th>
                                <td class="whitespace-pre-line px-5 py-3 break-words text-slate-600">{{ requirement.description || '—' }}</td>
                                <td class="px-5 py-3 text-right">
                                    <button type="button" :aria-label="`Edit ${requirement.name}`" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 px-3 py-1.5 font-medium text-[#0D47A1] hover:bg-blue-100" @click="openForm(requirement)">
                                        <Pencil aria-hidden="true" class="h-3.5 w-3.5" /> Edit
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="px-5 py-14 text-center">
                    <ClipboardList aria-hidden="true" class="mx-auto mb-3 h-8 w-8 text-blue-600" />
                    <p class="text-sm font-medium text-slate-700">No requirements for {{ transactionTypes[selectedType] }} yet.</p>
                </div>
            </section>
        </div>

        <dialog ref="dialog" aria-labelledby="requirement-form-title" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-md overflow-y-auto rounded-xl border border-blue-200 bg-white shadow-xl backdrop:bg-black/50" @cancel.prevent="closeForm">
            <form class="space-y-4 p-5" @submit.prevent="save">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="requirement-form-title" class="text-lg font-bold text-[#0D47A1]">{{ editingId ? 'Edit requirement' : 'Add requirement' }}</h2>
                    <button type="button" :disabled="saving" aria-label="Close requirement form" class="rounded-lg p-2 hover:bg-blue-50 disabled:opacity-50" @click="closeForm"><X aria-hidden="true" class="h-5 w-5" /></button>
                </div>
                <p v-if="saveError" role="alert" class="text-sm text-red-700">{{ saveError }}</p>
                <fieldset :disabled="saving" class="space-y-4">
                    <div>
                        <label for="requirement-type" class="mb-2 block text-sm font-medium">Transaction type</label>
                        <select id="requirement-type" v-model="form.transaction_type" required aria-describedby="requirement-type-error" :aria-invalid="!!errors.transaction_type" class="w-full rounded-lg border border-blue-300 bg-white px-3 py-2.5 text-sm">
                            <option v-for="(label, value) in transactionTypes" :key="value" :value="value">{{ label }}</option>
                        </select>
                        <p id="requirement-type-error" role="alert" class="text-sm text-red-700">{{ errors.transaction_type?.[0] }}</p>
                    </div>
                    <div>
                        <label for="requirement-name" class="mb-2 block text-sm font-medium">Requirement name</label>
                        <input v-uppercase id="requirement-name" v-model="form.name" required maxlength="191" autofocus aria-describedby="requirement-name-error" :aria-invalid="!!errors.name" class="w-full rounded-lg border border-blue-300 px-3 py-2.5 text-sm" />
                        <p id="requirement-name-error" role="alert" class="text-sm text-red-700">{{ errors.name?.[0] }}</p>
                    </div>
                    <div>
                        <label for="requirement-description" class="mb-2 block text-sm font-medium">Instructions <span class="font-normal text-slate-500">(optional)</span></label>
                        <textarea v-uppercase id="requirement-description" v-model="form.description" rows="3" maxlength="1000" aria-describedby="requirement-description-error" :aria-invalid="!!errors.description" class="w-full rounded-lg border border-blue-300 px-3 py-2.5 text-sm"></textarea>
                        <p id="requirement-description-error" role="alert" class="text-sm text-red-700">{{ errors.description?.[0] }}</p>
                    </div>
                </fieldset>
                <div class="flex justify-end gap-3 border-t border-blue-100 pt-4">
                    <button type="button" :disabled="saving" class="rounded-lg border border-slate-200 px-4 py-2 text-sm disabled:opacity-50" @click="closeForm">Cancel</button>
                    <button type="submit" :disabled="saving" class="rounded-lg bg-[#0D47A1] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900 disabled:opacity-50">{{ saving ? 'Saving...' : 'Save requirement' }}</button>
                </div>
            </form>
        </dialog>
    </AuthenticatedLayout>
</template>

<script setup>
const model = defineModel()
defineProps({ name: String, label: String, type: { type: String, default: 'text' }, options: Array, preserveCase: Boolean, required: Boolean, min: String, errors: { type: Object, default: () => ({}) } })
</script>
<template>
    <div>
        <label :for="`transaction-${name}`" class="mb-2 block text-sm font-medium text-gray-700">{{ label }}</label>
        <select v-if="options" :id="`transaction-${name}`" v-model="model" :required="required" :aria-invalid="!!errors[name]" :aria-describedby="`transaction-${name}-error`" class="w-full rounded-lg border border-[#90CAF9] bg-white px-3 py-2.5 text-sm">
            <option value="" disabled>Select {{ label.toLowerCase() }}</option>
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <textarea v-else-if="type === 'textarea'" v-uppercase :id="`transaction-${name}`" v-model="model" :required="required" maxlength="5000" rows="3" :aria-invalid="!!errors[name]" :aria-describedby="`transaction-${name}-error`" class="w-full rounded-lg border border-[#90CAF9] px-3 py-2.5 text-sm" />
        <input v-else v-uppercase :data-preserve-case="preserveCase || undefined" :id="`transaction-${name}`" v-model="model" :type="type" :required="required" :min="min" maxlength="5000" :aria-invalid="!!errors[name]" :aria-describedby="`transaction-${name}-error`" class="w-full rounded-lg border border-[#90CAF9] px-3 py-2.5 text-sm" />
        <p v-if="errors[name]" :id="`transaction-${name}-error`" role="alert" class="mt-1 text-sm text-red-600">{{ errors[name] }}</p>
    </div>
</template>

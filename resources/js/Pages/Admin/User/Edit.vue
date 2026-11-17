<script setup>
import UserForm from './Create.vue'

defineProps({
    user: { type: Object, default: null },
    roles: { type: Array, default: () => [] },
})
const open = defineModel('open', { type: Boolean, default: false })
const emit = defineEmits(['updated'])
</script>

<template>
    <UserForm v-model:open="open" :user="user" :roles="roles" @updated="emit('updated')" />
</template>

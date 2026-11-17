import { reactive } from 'vue'
import http from '@/lib/http'

export function useRoleForm() {
    const form = reactive({
        name: '',
        permissions: [],
        processing: false,
        errors: {},
        error: '',
        reset() { form.name = ''; form.permissions = [] },
        clearErrors() { form.errors = {}; form.error = '' },
        async submit(method, url) {
            if (form.processing) return false
            form.processing = true
            form.clearErrors()
            try {
                await http.request({ method, url, data: { name: form.name, permissions: form.permissions } })
                return true
            } catch (error) {
                if (error.response?.status === 422) {
                    form.errors = Object.fromEntries(Object.entries(error.response.data.errors ?? {})
                        .map(([field, messages]) => [field, Array.isArray(messages) ? messages[0] : messages]))
                } else {
                    form.error = error.response?.status === 419
                        ? 'Your session expired. Refresh the page and try again.'
                        : error.response?.status === 403 ? 'You do not have role to perform this action.'
                            : error.response?.status === 401 ? 'Your session ended. Sign in again.'
                                : 'Unable to save the role. Please try again.'
                }
                return false
            } finally {
                form.processing = false
            }
        },
    })
    return form
}


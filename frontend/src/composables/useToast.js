import { ref, reactive } from 'vue'

const toasts = reactive([])
let toastId = 0

export function useToast() {
    function show(options) {
        const id = ++toastId
        const toast = {
            id,
            type: options.type || 'info',
            title: options.title || '',
            message: options.message || '',
            duration: options.duration ?? 5000,
            show: true
        }
        toasts.push(toast)

        if (toast.duration > 0) {
            setTimeout(() => {
                remove(id)
            }, toast.duration)
        }

        return id
    }

    function remove(id) {
        const index = toasts.findIndex(t => t.id === id)
        if (index > -1) {
            toasts.splice(index, 1)
        }
    }

    function success(message, title = 'Success') {
        return show({ type: 'success', title, message })
    }

    function error(message, title = 'Error') {
        return show({ type: 'error', title, message })
    }

    function warning(message, title = 'Warning') {
        return show({ type: 'warning', title, message })
    }

    function info(message, title = 'Info') {
        return show({ type: 'info', title, message })
    }

    return {
        toasts,
        show,
        remove,
        success,
        error,
        warning,
        info
    }
}

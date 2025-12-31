import { ref, reactive } from 'vue'

const state = reactive({
    show: false,
    title: '',
    message: '',
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    type: 'danger', // danger, warning, info
    resolve: null
})

export function useConfirm() {
    function confirm(options) {
        return new Promise((resolve) => {
            state.show = true
            state.title = options.title || 'Confirm'
            state.message = options.message || 'Are you sure?'
            state.confirmText = options.confirmText || 'Confirm'
            state.cancelText = options.cancelText || 'Cancel'
            state.type = options.type || 'danger'
            state.resolve = resolve
        })
    }

    function handleConfirm() {
        state.show = false
        if (state.resolve) state.resolve(true)
    }

    function handleCancel() {
        state.show = false
        if (state.resolve) state.resolve(false)
    }

    // Helper methods
    function confirmDelete(itemName) {
        return confirm({
            title: 'Delete Confirmation',
            message: `Are you sure you want to delete "${itemName}"? This action cannot be undone.`,
            confirmText: 'Delete',
            cancelText: 'Cancel',
            type: 'danger'
        })
    }

    function confirmAction(message, title = 'Confirm Action') {
        return confirm({
            title,
            message,
            confirmText: 'Yes',
            cancelText: 'No',
            type: 'warning'
        })
    }

    return {
        state,
        confirm,
        confirmDelete,
        confirmAction,
        handleConfirm,
        handleCancel
    }
}

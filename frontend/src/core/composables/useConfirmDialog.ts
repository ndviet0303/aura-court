import { ref } from 'vue'

export interface ConfirmDialogState {
  isOpen: boolean
  title: string
  message: string
  confirmText?: string
  cancelText?: string
  resolve?: (value: boolean) => void
}

export function useConfirmDialog() {
  const state = ref<ConfirmDialogState>({
    isOpen: false,
    title: '',
    message: '',
    confirmText: 'Xác nhận',
    cancelText: 'Hủy',
  })

  function confirm(options: {
    title: string
    message: string
    confirmText?: string
    cancelText?: string
  }): Promise<boolean> {
    return new Promise((resolve) => {
      state.value = {
        isOpen: true,
        title: options.title,
        message: options.message,
        confirmText: options.confirmText || 'Xác nhận',
        cancelText: options.cancelText || 'Hủy',
        resolve,
      }
    })
  }

  function handleConfirm() {
    state.value.isOpen = false
    state.value.resolve?.(true)
  }

  function handleCancel() {
    state.value.isOpen = false
    state.value.resolve?.(false)
  }

  return {
    dialogState: state,
    confirm,
    handleConfirm,
    handleCancel,
  }
}

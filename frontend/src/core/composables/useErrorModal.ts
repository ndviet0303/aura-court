import { ref } from 'vue'
import { resolveErrorDescriptor, type StructuredErrorDescriptor } from '@/core/utils/errorMessage'

export interface ShowErrorOptions {
  onRetry?: () => Promise<void> | void
  operationKey?: string
  title?: string
}

export function useErrorModal() {
  const isOpen = ref(false)
  const currentError = ref<unknown>(null)
  const descriptor = ref<StructuredErrorDescriptor | null>(null)
  const retryHandler = ref<(() => Promise<void> | void) | null>(null)
  const operationKey = ref<string | null>(null)
  const customTitle = ref<string | undefined>(undefined)
  const isRetrying = ref(false)

  /**
   * Coalesce / deduplicate multiple concurrent errors into a single visible modal dialog.
   * If an error dialog is already open, subsequent concurrent failures update the descriptor
   * rather than creating stacked duplicate modals.
   */
  function showError(err: unknown, options?: ShowErrorOptions): void {
    currentError.value = err
    descriptor.value = resolveErrorDescriptor(err)
    if (options?.title) {
      customTitle.value = options.title
    }
    if (options?.onRetry) {
      retryHandler.value = options.onRetry
    }
    if (options?.operationKey) {
      // Retain the operation key for mutation retries without duplicating operations
      operationKey.value = options.operationKey
    }
    isOpen.value = true
  }

  function closeError(): void {
    isOpen.value = false
    // Retain operationKey and form inputs; do not wipe on dismiss
  }

  async function retry(): Promise<void> {
    if (retryHandler.value && !isRetrying.value) {
      isRetrying.value = true
      try {
        await retryHandler.value()
        closeError()
      } catch (retryErr) {
        showError(retryErr, {
          onRetry: retryHandler.value,
          operationKey: operationKey.value ?? undefined,
          title: customTitle.value,
        })
      } finally {
        isRetrying.value = false
      }
    }
  }

  return {
    isOpen,
    currentError,
    descriptor,
    operationKey,
    customTitle,
    isRetrying,
    showError,
    closeError,
    retry,
  }
}

import { ref } from 'vue'
import { errorMessage } from '@/core/utils/errorMessage'

export function useApiCall<T, Args extends unknown[]>(
  apiFn: (...args: Args) => Promise<T>,
  options: {
    initialLoading?: boolean
    defaultErrorMessage?: string
    onSuccess?: (data: T) => void
    onError?: (error: unknown) => void
  } = {},
) {
  const loading = ref(options.initialLoading ?? false)
  const error = ref<string | null>(null)
  const data = ref<T | null>(null)
  // Only the newest call may write state; an older answer belongs to arguments no longer shown.
  let latest = 0

  async function execute(...args: Args): Promise<T | null> {
    const request = ++latest
    loading.value = true
    error.value = null
    try {
      const result = await apiFn(...args)
      if (request === latest) {
        data.value = result as any
        options.onSuccess?.(result)
      }
      return result
    } catch (e) {
      if (request === latest) {
        error.value = errorMessage(e, options.defaultErrorMessage)
        options.onError?.(e)
      }
      throw e
    } finally {
      if (request === latest) loading.value = false
    }
  }

  function reset() {
    latest++
    loading.value = false
    error.value = null
    data.value = null
  }

  return {
    loading,
    error,
    data,
    execute,
    reset,
  }
}

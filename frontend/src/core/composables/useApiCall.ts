import { ref } from 'vue'
import { errorMessage } from '@/core/utils/errorMessage'

export function useApiCall<T, Args extends unknown[]>(
  apiFn: (...args: Args) => Promise<T>,
  options: {
    initialLoading?: boolean
    defaultErrorMessage?: string
    onSuccess?: (data: T) => void
    onError?: (error: unknown) => void
  } = {}
) {
  const loading = ref(options.initialLoading ?? false)
  const error = ref<string | null>(null)
  const data = ref<T | null>(null)

  async function execute(...args: Args): Promise<T | null> {
    loading.value = true
    error.value = null
    try {
      const result = await apiFn(...args)
      data.value = result as any
      options.onSuccess?.(result)
      return result
    } catch (e) {
      const msg = errorMessage(e, options.defaultErrorMessage)
      error.value = msg
      options.onError?.(e)
      throw e
    } finally {
      loading.value = false
    }
  }

  function reset() {
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

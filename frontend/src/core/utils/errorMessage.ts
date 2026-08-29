export function errorMessage(
  error: unknown,
  fallback: string = 'Đã có lỗi xảy ra. Vui lòng thử lại.'
): string {
  if (!error) return fallback
  if (typeof error === 'string') return error

  if (typeof error === 'object') {
    const err = error as Record<string, unknown>
    // Handle Laravel validation errors object: { errors: { field: ['message'] } }
    if (err.errors && typeof err.errors === 'object') {
      const fieldErrors = Object.values(err.errors as Record<string, string[]>)
      const firstError = fieldErrors.flat().find((msg) => typeof msg === 'string' && msg.length > 0)
      if (firstError) return firstError
    }

    if (typeof err.message === 'string' && err.message.length > 0) {
      return err.message
    }
  }

  return fallback
}

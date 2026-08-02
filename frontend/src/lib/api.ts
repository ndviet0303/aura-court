export type ApiError = { message: string; errors?: Record<string, string[]> }

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const xsrf = document.cookie.split('; ').find((part) => part.startsWith('XSRF-TOKEN='))?.split('=')[1]
  const response = await fetch(`/api/v1${path}`, {
    credentials: 'include', headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...(xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf) } : {}), ...(options.headers ?? {}) }, ...options,
  })
  const data = await response.json().catch(() => ({}))
  if (!response.ok) throw data as ApiError
  return data as T
}

export const api = {
  csrf: () => fetch('/sanctum/csrf-cookie', { credentials: 'include' }),
  get: <T>(path: string) => request<T>(path),
  post: <T>(path: string, body?: unknown) => request<T>(path, { method: 'POST', body: body ? JSON.stringify(body) : undefined }),
  patch: <T>(path: string, body: unknown) => request<T>(path, { method: 'PATCH', body: JSON.stringify(body) }),
}

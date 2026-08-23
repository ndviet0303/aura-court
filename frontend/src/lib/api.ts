import { getCurrentLocale } from '@/i18n'

export type ApiError = { message: string; errors?: Record<string, string[]> }

const buildApiOrigin = (import.meta.env.VITE_API_URL || '').replace(/\/$/, '')
export let apiOrigin = buildApiOrigin

// The Worker can provide a public API origin at runtime. Keep the compiled
// Vite value as a fallback so the app still works during local development and
// if the optional runtime variable has not been configured yet.
const runtimeConfigReady = fetch('/runtime-config.json', {
  headers: { Accept: 'application/json' },
})
  .then(async (response) => {
    if (!response.ok) return
    const config = (await response.json()) as { apiUrl?: unknown }
    if (typeof config.apiUrl === 'string' && config.apiUrl.length > 0) {
      apiOrigin = config.apiUrl.replace(/\/$/, '')
    }
  })
  .catch(() => undefined)

export const apiUrl = (path: string) => `${apiOrigin}/api/v1${path}`
export const apiV2Url = (path: string) => `${apiOrigin}/api/v2${path}`

export function errorMessage(error: unknown, fallback: string): string {
  if (typeof error === 'object' && error !== null && 'message' in error) {
    const message = (error as { message?: unknown }).message
    if (typeof message === 'string' && message.length > 0) return message
  }
  return fallback
}

async function csrfCookie(): Promise<Response> {
  await runtimeConfigReady

  return fetch(`${apiOrigin}/sanctum/csrf-cookie`, { credentials: 'include' })
}

function xsrfHeader(): Record<string, string> {
  const cookie = document.cookie.split('; ').find((part) => part.startsWith('XSRF-TOKEN='))
  const value = cookie?.slice(cookie.indexOf('=') + 1)

  return value ? { 'X-XSRF-TOKEN': decodeURIComponent(value) } : {}
}

function sendTo(url: string, options: RequestInit): Promise<Response> {
  return fetch(url, {
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'Accept-Language': getCurrentLocale(),
      ...xsrfHeader(),
      ...options.headers,
    },
    ...options,
  })
}

async function requestAt<T>(url: string, options: RequestInit = {}): Promise<T> {
  await runtimeConfigReady
  let response = await sendTo(url, options)

  // Laravel regenerates the CSRF token together with the session after login.
  // Refresh and retry once when a tab still holds the previous token. A 419 is
  // returned before the controller runs, so retrying cannot duplicate a write.
  if (response.status === 419) {
    await csrfCookie()
    response = await sendTo(url, options)
  }

  const data = await response.json().catch(() => ({}))
  if (!response.ok) throw data as ApiError
  return data as T
}

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  return requestAt<T>(apiUrl(path), options)
}

function idempotencyKey(): string {
  return typeof crypto.randomUUID === 'function' ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`
}

export const api = {
  csrf: csrfCookie,
  get: <T>(path: string) => request<T>(path),
  post: <T>(path: string, body?: unknown) => request<T>(path, { method: 'POST', body: body ? JSON.stringify(body) : undefined }),
  patch: <T>(path: string, body: unknown) => request<T>(path, { method: 'PATCH', body: JSON.stringify(body) }),
  delete: <T>(path: string) => request<T>(path, { method: 'DELETE' }),
}

export const apiV2 = {
  get: <T>(path: string) => requestAt<T>(apiV2Url(path)),
  post: <T>(path: string, body?: unknown, key = idempotencyKey()) => requestAt<T>(apiV2Url(path), { method: 'POST', body: body ? JSON.stringify(body) : undefined, headers: { 'Idempotency-Key': key } }),
  patch: <T>(path: string, body: unknown, key = idempotencyKey()) => requestAt<T>(apiV2Url(path), { method: 'PATCH', body: JSON.stringify(body), headers: { 'Idempotency-Key': key } }),
  put: <T>(path: string, body: unknown, key = idempotencyKey()) => requestAt<T>(apiV2Url(path), { method: 'PUT', body: JSON.stringify(body), headers: { 'Idempotency-Key': key } }),
  delete: <T>(path: string, key = idempotencyKey()) => requestAt<T>(apiV2Url(path), { method: 'DELETE', headers: { 'Idempotency-Key': key } }),
}

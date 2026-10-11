import { getCurrentLocale } from '@/i18n'
import type { ApiError, QueryParams, ApiRequestOptions } from '@/core/types/api'
import { errorMessage } from '@/core/utils/errorMessage'

export type { ApiError, QueryParams, ApiRequestOptions }
export { errorMessage }

const buildApiOrigin = (import.meta.env.VITE_API_URL || '').replace(/\/$/, '')
export let apiOrigin = buildApiOrigin

export function getApiOrigin(): string {
  return apiOrigin
}

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

function appendQueryParams(url: string, params?: QueryParams): string {
  if (!params) return url
  const entries = Object.entries(params).filter(
    ([_, value]) => value !== undefined && value !== null,
  )
  if (entries.length === 0) return url

  const separator = url.includes('?') ? '&' : '?'
  const searchParams = new URLSearchParams()
  for (const [key, val] of entries) {
    searchParams.append(key, String(val))
  }
  return `${url}${separator}${searchParams.toString()}`
}

export const apiUrl = (path: string, params?: QueryParams) =>
  appendQueryParams(`${apiOrigin}/api/v1${path.startsWith('/') ? path : `/${path}`}`, params)

export const apiV2Url = (path: string, params?: QueryParams) =>
  appendQueryParams(`${apiOrigin}/api/v2${path.startsWith('/') ? path : `/${path}`}`, params)

async function csrfCookie(): Promise<Response> {
  await runtimeConfigReady
  return fetch(`${apiOrigin}/sanctum/csrf-cookie`, { credentials: 'include' })
}

function xsrfHeader(): Record<string, string> {
  if (typeof document === 'undefined') return {}
  const cookie = document.cookie.split('; ').find((part) => part.startsWith('XSRF-TOKEN='))
  const value = cookie?.slice(cookie.indexOf('=') + 1)
  return value ? { 'X-XSRF-TOKEN': decodeURIComponent(value) } : {}
}

function normalizeBody(body: unknown): { bodyPayload?: BodyInit; isFormData: boolean } {
  if (body === undefined || body === null) {
    return { bodyPayload: undefined, isFormData: false }
  }
  if (typeof FormData !== 'undefined' && body instanceof FormData) {
    return { bodyPayload: body, isFormData: true }
  }
  if (typeof body === 'string' || body instanceof Blob || body instanceof ArrayBuffer) {
    return { bodyPayload: body as BodyInit, isFormData: false }
  }
  return { bodyPayload: JSON.stringify(body), isFormData: false }
}

function sendTo(url: string, options: RequestInit): Promise<Response> {
  const isFormData = typeof FormData !== 'undefined' && options.body instanceof FormData
  const baseHeaders: Record<string, string> = {
    Accept: 'application/json',
    'Accept-Language': getCurrentLocale(),
    ...xsrfHeader(),
  }

  if (!isFormData) {
    baseHeaders['Content-Type'] = 'application/json'
  }

  return fetch(url, {
    credentials: 'include',
    ...options,
    headers: {
      ...baseHeaders,
      ...(options.headers as Record<string, string> | undefined),
    },
  })
}

async function requestAt<T>(url: string, options: RequestInit = {}): Promise<T> {
  await runtimeConfigReady
  let response: Response
  try {
    response = await sendTo(url, options)
  } catch (cause: unknown) {
    throw {
      message: 'Lỗi kết nối mạng. Vui lòng kiểm tra lại đường truyền.',
      code: 'NETWORK_ERROR',
      status: 0,
      cause,
    } as ApiError
  }

  // Laravel regenerates the CSRF token together with the session after login.
  // Refresh and retry once when a tab still holds the previous token. A 419 is
  // returned before the controller runs, so retrying cannot duplicate a write.
  if (response.status === 419) {
    await csrfCookie()
    try {
      response = await sendTo(url, options)
    } catch (cause: unknown) {
      throw {
        message: 'Lỗi kết nối mạng. Vui lòng kiểm tra lại đường truyền.',
        code: 'NETWORK_ERROR',
        status: 0,
        cause,
      } as ApiError
    }
  }

  const data = await response.json().catch(() => ({}))
  if (!response.ok) {
    let code = data?.code
    if (!code) {
      if (response.status === 401) code = 'UNAUTHORIZED'
      else if (response.status === 403) code = 'FORBIDDEN'
      else if (response.status === 404) code = 'NOT_FOUND'
      else if (response.status === 409) code = 'RESOURCE_CONFLICT'
      else if (response.status === 422) code = 'VALIDATION_ERROR'
      else if (response.status >= 500) code = 'SERVER_ERROR'
    }

    throw {
      message: data?.message || `Yêu cầu thất bại (${response.status})`,
      errors: data?.errors,
      status: response.status,
      code,
      ...data,
    } as ApiError
  }
  return data as T
}

export function idempotencyKey(): string {
  return typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function'
    ? crypto.randomUUID()
    : `${Date.now()}-${Math.random().toString(36).substring(2, 9)}`
}

function parseOptions(optOrKey?: ApiRequestOptions | string): ApiRequestOptions {
  if (!optOrKey) return {}
  if (typeof optOrKey === 'string') {
    return { idempotencyKey: optOrKey }
  }
  return optOrKey
}

function buildHeaders(
  options?: ApiRequestOptions,
  defaultIdempotent = false,
): Record<string, string> {
  const headers: Record<string, string> = {
    ...((options?.headers as Record<string, string>) || {}),
  }
  if (options?.idempotencyKey) {
    headers['Idempotency-Key'] = options.idempotencyKey
  } else if (defaultIdempotent && !headers['Idempotency-Key']) {
    headers['Idempotency-Key'] = idempotencyKey()
  }
  return headers
}

/**
 * API v1 Client
 */
export const api = {
  csrf: csrfCookie,
  get: <T>(path: string, options?: ApiRequestOptions) => {
    const opts = parseOptions(options)
    return requestAt<T>(apiUrl(path, opts.params), {
      ...opts,
      method: 'GET',
      headers: buildHeaders(opts),
    })
  },
  post: <T>(path: string, body?: unknown, options?: ApiRequestOptions | string) => {
    const opts = parseOptions(options)
    const { bodyPayload } = normalizeBody(body)
    return requestAt<T>(apiUrl(path, opts.params), {
      ...opts,
      method: 'POST',
      body: bodyPayload,
      headers: buildHeaders(opts),
    })
  },
  put: <T>(path: string, body?: unknown, options?: ApiRequestOptions | string) => {
    const opts = parseOptions(options)
    const { bodyPayload } = normalizeBody(body)
    return requestAt<T>(apiUrl(path, opts.params), {
      ...opts,
      method: 'PUT',
      body: bodyPayload,
      headers: buildHeaders(opts),
    })
  },
  patch: <T>(path: string, body?: unknown, options?: ApiRequestOptions | string) => {
    const opts = parseOptions(options)
    const { bodyPayload } = normalizeBody(body)
    return requestAt<T>(apiUrl(path, opts.params), {
      ...opts,
      method: 'PATCH',
      body: bodyPayload,
      headers: buildHeaders(opts),
    })
  },
  delete: <T>(path: string, options?: ApiRequestOptions | string) => {
    const opts = parseOptions(options)
    return requestAt<T>(apiUrl(path, opts.params), {
      ...opts,
      method: 'DELETE',
      headers: buildHeaders(opts),
    })
  },
}

/**
 * API v2 Client (with automatic idempotency header support for mutations)
 */
export const apiV2 = {
  csrf: csrfCookie,
  get: <T>(path: string, options?: ApiRequestOptions) => {
    const opts = parseOptions(options)
    return requestAt<T>(apiV2Url(path, opts.params), {
      ...opts,
      method: 'GET',
      headers: buildHeaders(opts),
    })
  },
  post: <T>(path: string, body?: unknown, options?: ApiRequestOptions | string) => {
    const opts = parseOptions(options)
    const { bodyPayload } = normalizeBody(body)
    return requestAt<T>(apiV2Url(path, opts.params), {
      ...opts,
      method: 'POST',
      body: bodyPayload,
      headers: buildHeaders(opts, true),
    })
  },
  put: <T>(path: string, body?: unknown, options?: ApiRequestOptions | string) => {
    const opts = parseOptions(options)
    const { bodyPayload } = normalizeBody(body)
    return requestAt<T>(apiV2Url(path, opts.params), {
      ...opts,
      method: 'PUT',
      body: bodyPayload,
      headers: buildHeaders(opts, true),
    })
  },
  patch: <T>(path: string, body?: unknown, options?: ApiRequestOptions | string) => {
    const opts = parseOptions(options)
    const { bodyPayload } = normalizeBody(body)
    return requestAt<T>(apiV2Url(path, opts.params), {
      ...opts,
      method: 'PATCH',
      body: bodyPayload,
      headers: buildHeaders(opts, true),
    })
  },
  delete: <T>(path: string, options?: ApiRequestOptions | string) => {
    const opts = parseOptions(options)
    return requestAt<T>(apiV2Url(path, opts.params), {
      ...opts,
      method: 'DELETE',
      headers: buildHeaders(opts, true),
    })
  },
}

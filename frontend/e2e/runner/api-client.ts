import { randomUUID } from 'node:crypto'
import type { APIRequestContext, Page } from '@playwright/test'
import type { RunManifest } from './types.ts'

export type ApiClientOptions = {
  headers?: Record<string, string>
  params?: Record<string, string | number | boolean | null | undefined>
  multipart?: NonNullable<Parameters<APIRequestContext['post']>[1]>['multipart']
  idempotencyKey?: string
}

export class E2EApiError extends Error {
  readonly status: number
  readonly response: { status: number; data: Record<string, unknown> }
  readonly errors: unknown

  constructor(status: number, body: Record<string, unknown>, method: string, path: string) {
    super(
      typeof body.message === 'string'
        ? body.message
        : `API ${method} ${path} failed with ${status}`,
    )
    this.status = status
    this.response = { status, data: body }
    this.errors = body.errors
  }
}

/** Real HTTP requests share the browser's cookies without intercepting the application. */
export function createE2EApiClient(page: Page, manifest: RunManifest, version: 'v1' | 'v2' = 'v1') {
  const backendUrl = manifest.backend.url.replace(/\/$/, '')
  const csrf = () =>
    page.request.get(`${backendUrl}/sanctum/csrf-cookie`, {
      headers: {
        Accept: 'application/json',
        Origin: manifest.frontend.url,
        Referer: manifest.frontend.url,
      },
    })

  async function request<T = any>(
    method: string,
    path: string,
    data?: unknown,
    opt?: ApiClientOptions | string,
  ): Promise<T> {
    const options = typeof opt === 'string' ? { idempotencyKey: opt } : opt || {}
    const headers: Record<string, string> = { ...options.headers }
    if (options.idempotencyKey) headers['Idempotency-Key'] = options.idempotencyKey
    else if (version === 'v2' && method !== 'GET' && !headers['Idempotency-Key'])
      headers['Idempotency-Key'] = randomUUID()
    const cleanPath = path.startsWith('/') ? path : `/${path}`
    const url = new URL(
      `${backendUrl}${cleanPath.startsWith('/api/') ? cleanPath : `/api/${version}${cleanPath}`}`,
    )
    for (const [key, value] of Object.entries(options.params || {})) {
      if (value !== null && value !== undefined) url.searchParams.append(key, String(value))
    }

    const send = async () => {
      const cookies = await page.context().cookies(backendUrl)
      const xsrf = cookies.find((cookie) => cookie.name === 'XSRF-TOKEN')
      return page.request.fetch(url.toString(), {
        method,
        headers: {
          Accept: 'application/json',
          Origin: manifest.frontend.url,
          Referer: manifest.frontend.url,
          ...(xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf.value) } : {}),
          ...headers,
        },
        ...(options.multipart
          ? { multipart: options.multipart }
          : data instanceof FormData
            ? { multipart: data }
            : { data }),
      })
    }
    let response = await send()
    // Laravel rejects 419 before the operation runs, so refreshing CSRF and retrying is safe.
    if (response.status() === 419) {
      await csrf()
      response = await send()
    }
    const body = (await response.json().catch(() => ({}))) as Record<string, unknown>
    if (!response.ok()) throw new E2EApiError(response.status(), body, method, path)
    return body as T
  }

  return {
    csrf,
    get: <T = any>(path: string, options?: ApiClientOptions) =>
      request<T>('GET', path, undefined, options),
    post: <T = any>(path: string, data?: unknown, options?: ApiClientOptions | string) =>
      request<T>('POST', path, data, options),
    put: <T = any>(path: string, data?: unknown, options?: ApiClientOptions | string) =>
      request<T>('PUT', path, data, options),
    patch: <T = any>(path: string, data?: unknown, options?: ApiClientOptions | string) =>
      request<T>('PATCH', path, data, options),
    delete: <T = any>(path: string, options?: ApiClientOptions | string) =>
      request<T>('DELETE', path, undefined, options),
  }
}

/** Stop background app requests before invalidating the actor's shared server session. */
export async function logoutE2EActor(page: Page, manifest: RunManifest): Promise<void> {
  await page.evaluate(() => {
    localStorage.clear()
    sessionStorage.clear()
  })
  await page.goto('about:blank')
  await createE2EApiClient(page, manifest).post('/auth/logout')
  await page.context().clearCookies()
}

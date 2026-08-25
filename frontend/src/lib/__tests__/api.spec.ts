import { beforeEach, describe, expect, it, vi } from 'vitest'

describe('API CSRF recovery', () => {
  beforeEach(() => {
    vi.resetModules()
    document.cookie = 'XSRF-TOKEN=initial; path=/'
  })

  it('refreshes the CSRF cookie and retries a 419 response once', async () => {
    let bookingCalls = 0
    const fetchMock = vi.fn<typeof fetch>(async (input: RequestInfo | URL, _init?: RequestInit): Promise<Response> => {
      const url = String(input)

      if (url === '/runtime-config.json') {
        return new Response(JSON.stringify({ apiUrl: 'https://api.example.test' }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        })
      }

      if (url.endsWith('/sanctum/csrf-cookie')) {
        document.cookie = 'XSRF-TOKEN=refreshed; path=/'

        return new Response(null, { status: 204 })
      }

      if (url.endsWith('/api/v1/bookings')) {
        bookingCalls += 1
      }

      return new Response(
        JSON.stringify(bookingCalls === 1 ? { message: 'CSRF token mismatch.' } : { ok: true }),
        {
          status: bookingCalls === 1 ? 419 : 200,
          headers: { 'Content-Type': 'application/json' },
        },
      )
    })
    vi.stubGlobal('fetch', fetchMock)

    const { api } = await import('../api')

    await expect(api.post('/bookings', {})).resolves.toEqual({ ok: true })
    expect(fetchMock).toHaveBeenCalledTimes(4)

    const retriedRequest = fetchMock.mock.calls[3]?.[1]
    expect(new Headers(retriedRequest?.headers).get('X-XSRF-TOKEN')).toBe('refreshed')
  })

  it('preserves Content-Type and custom headers on apiV2 requests', async () => {
    const fetchMock = vi.fn<typeof fetch>(async (input: RequestInfo | URL, init?: RequestInit): Promise<Response> => {
      const url = String(input)
      if (url === '/runtime-config.json') {
        return new Response(JSON.stringify({ apiUrl: 'https://api.example.test' }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        })
      }
      return new Response(JSON.stringify({ success: true, headers: init?.headers }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
    })
    vi.stubGlobal('fetch', fetchMock)

    const { apiV2 } = await import('../api')
    await apiV2.post('/recurring-series', { court_id: 1 })

    const postCall = fetchMock.mock.calls[1]
    const headers = postCall?.[1]?.headers as Record<string, string>
    expect(headers['Content-Type']).toBe('application/json')
    expect(headers['Idempotency-Key']).toBeDefined()
  })
})

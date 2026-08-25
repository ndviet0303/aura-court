import { beforeEach, describe, expect, it, vi } from 'vitest'

describe('API Client & Helpers', () => {
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

  it('appends query params correctly for GET requests in api and apiV2', async () => {
    const fetchMock = vi.fn<typeof fetch>(async (input: RequestInfo | URL): Promise<Response> => {
      const url = String(input)
      if (url === '/runtime-config.json') {
        return new Response(JSON.stringify({ apiUrl: 'https://api.example.test' }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        })
      }
      return new Response(JSON.stringify({ url, ok: true }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
    })
    vi.stubGlobal('fetch', fetchMock)

    const { api, apiV2 } = await import('../api')
    const res1 = await api.get<{ url: string }>('/courts', { params: { status: 'open', page: 2 } })
    expect(res1.url).toBe('https://api.example.test/api/v1/courts?status=open&page=2')

    const res2 = await apiV2.get<{ url: string }>('/marketplace/listings', { params: { venue: 'center' } })
    expect(res2.url).toBe('https://api.example.test/api/v2/marketplace/listings?venue=center')
  })

  it('extracts error messages from various error formats using errorMessage', async () => {
    const { errorMessage } = await import('../api')

    expect(errorMessage('Lỗi trực tiếp')).toBe('Lỗi trực tiếp')
    expect(errorMessage({ message: 'Thông báo lỗi từ API' })).toBe('Thông báo lỗi từ API')
    expect(errorMessage({ errors: { phone: ['Số điện thoại không hợp lệ'] } })).toBe('Số điện thoại không hợp lệ')
    expect(errorMessage(null, 'Lỗi mặc định')).toBe('Lỗi mặc định')
  })
})

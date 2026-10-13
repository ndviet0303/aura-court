import { beforeEach, describe, expect, it, vi } from 'vitest'

describe('API Client & Helpers', () => {
  beforeEach(() => {
    vi.resetModules()
    document.cookie = 'XSRF-TOKEN=initial; path=/'
  })

  it('refreshes the CSRF cookie and retries a 419 response once', async () => {
    let bookingCalls = 0
    const fetchMock = vi.fn<typeof fetch>(
      async (input: RequestInfo | URL, _init?: RequestInit): Promise<Response> => {
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

        if (url.endsWith('/api/reservations/checkout')) {
          bookingCalls += 1
        }

        return new Response(
          JSON.stringify(bookingCalls === 1 ? { message: 'CSRF token mismatch.' } : { ok: true }),
          {
            status: bookingCalls === 1 ? 419 : 200,
            headers: { 'Content-Type': 'application/json' },
          },
        )
      },
    )
    vi.stubGlobal('fetch', fetchMock)

    const { api } = await import('../api')

    await expect(api.post('/reservations/checkout', {})).resolves.toEqual({ ok: true })
    expect(fetchMock).toHaveBeenCalledTimes(4)

    const firstHeaders = new Headers(fetchMock.mock.calls[1]?.[1]?.headers)
    const retriedHeaders = new Headers(fetchMock.mock.calls[3]?.[1]?.headers)
    expect(retriedHeaders.get('X-XSRF-TOKEN')).toBe('refreshed')
    // The retry replays the same command, so it must keep the same Idempotency-Key.
    expect(retriedHeaders.get('Idempotency-Key')).toBe(firstHeaders.get('Idempotency-Key'))
  })

  it('preserves Content-Type and adds an Idempotency-Key to mutations', async () => {
    const fetchMock = vi.fn<typeof fetch>(
      async (input: RequestInfo | URL, init?: RequestInit): Promise<Response> => {
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
      },
    )
    vi.stubGlobal('fetch', fetchMock)

    const { api } = await import('../api')
    await api.post('/recurring-series', { court_id: 1 })
    await api.post('/recurring-series', { court_id: 1 }, 'caller-key')

    const headers = fetchMock.mock.calls[1]?.[1]?.headers as Record<string, string>
    expect(headers['Content-Type']).toBe('application/json')
    expect(headers['Idempotency-Key']).toBeDefined()
    const callerHeaders = fetchMock.mock.calls[2]?.[1]?.headers as Record<string, string>
    expect(callerHeaders['Idempotency-Key']).toBe('caller-key')
  })

  it('appends query params to unversioned GET requests', async () => {
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

    const { api } = await import('../api')
    const res1 = await api.get<{ url: string }>('/courts', { params: { status: 'open', page: 2 } })
    expect(res1.url).toBe('https://api.example.test/api/courts?status=open&page=2')

    const res2 = await api.get<{ url: string }>('/marketplace/listings', {
      params: { venue: 'center' },
    })
    expect(res2.url).toBe('https://api.example.test/api/marketplace/listings?venue=center')
  })

  it('extracts error messages from various error formats using errorMessage', async () => {
    const { errorMessage } = await import('../api')

    expect(errorMessage('Lỗi trực tiếp')).toBe('Lỗi trực tiếp')
    expect(errorMessage({ message: 'Thông báo lỗi từ API' })).toBe('Thông báo lỗi từ API')
    expect(errorMessage({ errors: { phone: ['Số điện thoại không hợp lệ'] } })).toBe(
      'Số điện thoại không hợp lệ',
    )
    expect(errorMessage(null, 'Lỗi mặc định')).toBe('Lỗi mặc định')
  })
})

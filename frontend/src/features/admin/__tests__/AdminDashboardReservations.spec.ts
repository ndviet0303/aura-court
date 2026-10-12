import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import AdminView from '../views/AdminView.vue'
import { RESERVATION_STATUSES } from '../composables/useAdminDashboard'
import { i18n } from '@/i18n'
import { api, apiV2 } from '@/core/api/client'

vi.mock('@/core/api/client', () => ({
  apiOrigin: '',
  api: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
  apiV2: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
  errorMessage: (_err: unknown, fallback: string) => fallback,
}))

const legacyMirror = {
  id: 1,
  code: 'LEGACY-BK-OLD1',
  status: 'legacy',
  total_amount: 200000,
  user: { name: 'Khách Cũ', email: 'old@example.test' },
  court: { id: 3, name: 'Sân 3' },
  slots: [{ starts_at: '2026-09-01T08:00:00+07:00', ends_at: '2026-09-01T09:00:00+07:00' }],
  legacy_mapping: [{ legacy_code: 'BK-OLD1', legacy_status: 'no_show' }],
}

const v2Reservation = {
  id: 2,
  code: 'RSV-NEW1',
  status: 'held',
  total_amount: 150000,
  user: { name: 'Khách Mới', email: 'new@example.test' },
  court: { id: 4, name: 'Sân 4' },
  slots: [{ starts_at: '2026-10-12T08:00:00+07:00', ends_at: '2026-10-12T09:00:00+07:00' }],
  legacy_mapping: [],
}

function mountDashboard() {
  return mount(AdminView, {
    global: {
      plugins: [i18n],
      stubs: {
        AdminDateRangeFilter: true,
        AdminKpiCards: true,
        AdminAnalyticsCharts: true,
        AdminPendingPaymentsCard: true,
        AdminQuickNav: true,
      },
    },
  })
}

describe('Admin dashboard reservations list (GET v2/admin/reservations)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(api.get).mockImplementation(async (path: string) =>
      path === '/admin/payments'
        ? []
        : { bookings: 0, confirmed: 0, revenue: 0, pendingRefunds: 0 },
    )
    vi.mocked(apiV2.get).mockResolvedValue({ data: [legacyMirror, v2Reservation], current_page: 1 })
  })

  it('reads the V2 admin reservations endpoint and never the V1 bookings list', async () => {
    mountDashboard()
    await flushPromises()

    expect(apiV2.get).toHaveBeenCalledWith('/admin/reservations', {
      params: { status: undefined },
    })
    const v1Paths = vi.mocked(api.get).mock.calls.map(([path]) => path)
    expect(v1Paths.some((path) => path.startsWith('/admin/bookings'))).toBe(false)
  })

  it('shows a legacy mirror by its original booking code and status', async () => {
    const wrapper = mountDashboard()
    await flushPromises()

    const rows = wrapper.findAll('.booking-row')
    expect(rows).toHaveLength(2)
    expect(wrapper.text()).not.toContain('LEGACY-')
    expect(rows[0]!.find('strong').text()).toBe('BK-OLD1')
    expect(rows[0]!.find('.status-pill').classes()).toContain('no_show')
    expect(rows[1]!.find('strong').text()).toBe('RSV-NEW1')
    expect(rows[1]!.find('.status-pill').classes()).toContain('held')
  })

  it('offers the C4 statuses and filters on the server', async () => {
    const wrapper = mountDashboard()
    await flushPromises()

    const select = wrapper.find('select.status-select')
    const values = select.findAll('option').map((option) => option.attributes('value'))
    expect(values).toEqual(['', ...RESERVATION_STATUSES])

    await select.setValue('legacy')
    await flushPromises()

    expect(apiV2.get).toHaveBeenLastCalledWith('/admin/reservations', {
      params: { status: 'legacy' },
    })
  })

  it('searches the displayed booking code of a legacy mirror', async () => {
    const wrapper = mountDashboard()
    await flushPromises()

    await wrapper.find('input.search-input').setValue('bk-old')

    const rows = wrapper.findAll('.booking-row')
    expect(rows).toHaveLength(1)
    expect(rows[0]!.find('strong').text()).toBe('BK-OLD1')
  })
})

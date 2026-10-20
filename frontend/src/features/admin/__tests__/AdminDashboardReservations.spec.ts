import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import AdminView from '../views/AdminView.vue'
import { RESERVATION_STATUSES } from '../composables/useAdminDashboard'
import { i18n } from '@/i18n'
import { api } from '@/core/api/client'

vi.mock('@/core/api/client', () => ({
  apiUrl: (p: string) => `/api${p}`,
  api: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
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

const reservation = {
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

describe('Admin dashboard reservations list (GET /admin/reservations)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(api.get).mockImplementation(async (path: string) => {
      if (path === '/admin/reservations')
        return { data: [legacyMirror, reservation], current_page: 1 }
      if (path === '/admin/payments') return []
      return { bookings: 0, confirmed: 0, revenue: 0 }
    })
  })

  it('reads the admin reservations endpoint and never the legacy bookings list', async () => {
    mountDashboard()
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/admin/reservations', {
      params: { status: undefined },
    })
    const paths = vi.mocked(api.get).mock.calls.map(([path]) => path)
    expect(paths.some((path) => path.startsWith('/admin/bookings'))).toBe(false)
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

  it('offers the C4 statuses without the V1 option and filters on the server', async () => {
    const wrapper = mountDashboard()
    await flushPromises()

    await wrapper.find('.status-select .custom-dropdown-trigger').trigger('click')
    const options = wrapper.findAll('.status-select [role="option"]')
    expect(options).toHaveLength(RESERVATION_STATUSES.length + 1)
    expect(RESERVATION_STATUSES).not.toContain('legacy')

    await options[RESERVATION_STATUSES.indexOf('disputed') + 1]!.trigger('click')
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/admin/reservations', {
      params: { status: 'disputed' },
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

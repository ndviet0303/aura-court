import { beforeEach, describe, expect, it, vi } from 'vitest'

const posApi = vi.hoisted(() => ({ getActiveShift: vi.fn(), getShifts: vi.fn() }))
const adminApi = vi.hoisted(() => ({ getVenues: vi.fn() }))

vi.mock('vue', async (importOriginal) => ({
  ...(await importOriginal<typeof import('vue')>()),
  onMounted: vi.fn(),
}))
vi.mock('@/features/pos/api/posApi', () => ({ posApi }))
vi.mock('@/features/admin/api/adminApi', () => ({ adminApi }))

import { useAdminShiftReconciliation } from '../useAdminShiftReconciliation'

describe('useAdminShiftReconciliation shift venues', () => {
  beforeEach(() => {
    adminApi.getVenues.mockResolvedValue([
      { id: 1, name: 'Cầu Giấy' },
      { id: 2, name: 'Đống Đa' },
    ])
    posApi.getActiveShift.mockResolvedValue(null)
    posApi.getShifts.mockResolvedValue([
      { id: 7, status: 'closed', cashier_id: 5, venue_id: 2, venue: { id: 2, name: 'Đống Đa' } },
      { id: 8, status: 'closed', cashier_id: 5, venue_id: null, venue: null },
    ])
  })

  it('shows each shift at its own venue and keeps venue-less shifts out of venue filters', async () => {
    const page = useAdminShiftReconciliation()
    await page.loadData()

    const [atB, legacy] = page.shiftHistory.value
    expect(atB).toMatchObject({ venue_id: 2, venue_name: 'Đống Đa' })
    expect(legacy).toMatchObject({ venue_id: undefined, venue_name: 'Chưa gắn cơ sở' })

    page.selectedVenueId.value = 1
    expect(page.filteredHistory.value).toEqual([])
    page.selectedVenueId.value = 2
    expect(page.filteredHistory.value.map((shift) => shift.id)).toEqual([7])
  })

  it('reads a venue name stored per locale', async () => {
    posApi.getShifts.mockResolvedValue([
      {
        id: 9,
        status: 'closed',
        venue_id: 1,
        venue: { id: 1, name: { vi: 'Cầu Giấy', en: 'Cau Giay' } },
      },
    ])
    const page = useAdminShiftReconciliation()
    await page.loadData()

    expect(page.shiftHistory.value[0]?.venue_name).toBe('Cầu Giấy')
  })
})

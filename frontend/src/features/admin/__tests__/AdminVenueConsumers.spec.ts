import type * as VueI18n from 'vue-i18n'
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { adminApi } from '../api/adminApi'
import { useAdminManageCourts } from '../composables/useAdminManageCourts'
import { useAdminVenues } from '../composables/useAdminVenues'
import { useAdminStaff } from '../composables/useAdminStaff'
import { useAdminSchedule } from '../composables/useAdminSchedule'
import { usePricingCalendar } from '../composables/usePricingCalendar'
import { useOrganizationOwner } from '../composables/useOrganizationOwner'
import { useAdminShiftReconciliation } from '../composables/useAdminShiftReconciliation'
import { api } from '@/core/api/client'
import type { Venue } from '@/features/court/types/court'
vi.mock('vue-i18n', async (importOriginal) => {
  const actual = await importOriginal<typeof VueI18n>()
  return {
    ...actual,
    useI18n: () => ({
      t: (key: string) => key,
    }),
  }
})

vi.mock('@/core/api/client', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
  errorMessage: (_err: unknown, fallback: string) => fallback,
}))

vi.mock('@/features/pos/api/posApi', () => ({
  posApi: {
    getActiveShift: vi.fn().mockResolvedValue(null),
    getShifts: vi.fn().mockResolvedValue([]),
    openShift: vi.fn().mockResolvedValue({ id: 1 }),
    closeShift: vi.fn().mockResolvedValue({ id: 1 }),
    reviewShift: vi.fn().mockResolvedValue({ id: 1 }),
  },
}))

describe('Admin Venue API & Consumers (Task 2.3 & 2.4)', () => {
  const mockVenues: Venue[] = [
    {
      id: 1,
      name: 'Aura Center',
      slug: 'aura-center',
      address: '142 Wellness Blvd, Downtown',
      google_maps_url: 'https://maps.google.com/?q=142+Wellness+Blvd',
      is_active: true,
      courts_count: 12,
    },
    {
      id: 2,
      name: 'Riverside Club',
      slug: 'riverside-club',
      address: '88 Waterway Point, East Side',
      google_maps_url: 'https://maps.google.com/?q=88+Waterway+Point',
      is_active: true,
      courts_count: 8,
    },
  ]

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()

    vi.mocked(api.get).mockImplementation(async (url: string) => {
      if (url === '/admin/venues') return mockVenues
      if (url === '/admin/courts') return []
      if (url.startsWith('/admin/sport-zones')) return []
      if (url === '/admin/refunds') return []
      if (url === '/admin/coupons') return []
      if (url === '/admin/audit-logs') return { data: [] }
      if (url.includes('/members')) return { members: [] }
      if (url === '/organizations') return { organizations: [{ id: 1, name: 'Org 1' }] }
      if (url.startsWith('/organizations/')) return { organization: { id: 1, name: 'Org 1' } }
      if (url === '/price-rules' || url === '/pricing/calendar') return { rules: [] }
      return []
    })
  })

  describe('adminApi.getVenues endpoint routing and CRUD compatibility', () => {
    it('calls the /admin/venues endpoint', async () => {
      const result = await adminApi.getVenues()

      expect(api.get).toHaveBeenCalledWith('/admin/venues')
      expect(result).toEqual(mockVenues)
    })

    it('retains CRUD compatibility', async () => {
      vi.mocked(api.post).mockResolvedValueOnce(mockVenues[0])
      vi.mocked(api.patch).mockResolvedValueOnce({ ...mockVenues[0], name: 'Updated Venue' })
      vi.mocked(api.delete).mockResolvedValueOnce({ message: 'Deleted' })

      const newVenuePayload = {
        name: 'New Venue',
        address: '123 New St',
        google_maps_url: 'https://maps.google.com/?q=123',
      }
      const created = await adminApi.createVenue(newVenuePayload)
      expect(api.post).toHaveBeenCalledWith('/admin/venues', newVenuePayload)
      expect(created).toEqual(mockVenues[0])

      const updated = await adminApi.updateVenue(1, { name: 'Updated Venue' })
      expect(api.patch).toHaveBeenCalledWith('/admin/venues/1', { name: 'Updated Venue' })
      expect(updated).toEqual({ ...mockVenues[0], name: 'Updated Venue' })

      const deleted = await adminApi.deleteVenue(1)
      expect(api.delete).toHaveBeenCalledWith('/admin/venues/1')
      expect(deleted).toEqual({ message: 'Deleted' })
    })
  })

  describe('Caller: /admin/manage (useAdminManageCourts)', () => {
    it('loads venues and populates state', async () => {
      const { venues, load, error } = useAdminManageCourts()
      await load()

      expect(api.get).toHaveBeenCalledWith('/admin/venues')
      expect(venues.value).toEqual(mockVenues)
      expect(error.value).toBe('')
    })

    it('surfaces error when /admin/venues fails rather than silently substituting success', async () => {
      vi.mocked(api.get).mockImplementation(async (url: string) => {
        if (url === '/admin/venues') throw new Error('Network error')
        return []
      })

      const { venues, load, error } = useAdminManageCourts()
      await load()

      expect(venues.value).toEqual([])
      expect(error.value).not.toBe('')
    })
  })

  describe('Caller: /admin/venues (useAdminVenues)', () => {
    it('loads venues and populates state', async () => {
      const { venues, loadData, error } = useAdminVenues()
      await loadData()

      expect(api.get).toHaveBeenCalledWith('/admin/venues')
      expect(venues.value.length).toBe(2)
      expect(venues.value[0]?.name).toBe('Aura Center')
      expect(error.value).toBe('')
    })

    it('surfaces error when /admin/venues fails', async () => {
      vi.mocked(api.get).mockImplementation(async (url: string) => {
        if (url === '/admin/venues') throw new Error('API down')
        return []
      })

      const { venues, loadData, error } = useAdminVenues()
      await loadData()

      expect(venues.value).toEqual([])
      expect(error.value).not.toBe('')
    })
  })

  describe('Caller: Staff (useAdminStaff)', () => {
    it('loads venues and populates venuesList', async () => {
      const { venuesList, loadStaffData, error } = useAdminStaff()
      await loadStaffData()

      expect(api.get).toHaveBeenCalledWith('/admin/venues')
      expect(venuesList.value.length).toBe(2)
      expect(error.value).toBe('')
    })

    it('surfaces error when venues endpoint fails', async () => {
      vi.mocked(api.get).mockImplementation(async (url: string) => {
        if (url === '/admin/venues') throw new Error('Venues 500')
        return []
      })

      const { loadStaffData, error } = useAdminStaff()
      await loadStaffData()

      expect(error.value).not.toBe('')
    })
  })

  describe('Caller: Schedule (useAdminSchedule)', () => {
    it('loads venues and populates state', async () => {
      const { venues, loadData, error } = useAdminSchedule()
      await loadData()
      expect(api.get).toHaveBeenCalledWith('/admin/venues')
      expect(venues.value).toEqual(mockVenues)
      expect(error.value).toBe('')
    })

    it('surfaces error when venues endpoint fails', async () => {
      vi.mocked(api.get).mockImplementation(async (url: string) => {
        if (url === '/admin/venues') throw new Error('Venues 500')
        return []
      })

      const { loadData, error } = useAdminSchedule()
      await loadData()
      expect(error.value).not.toBe('')
    })
  })

  describe('Caller: Pricing (usePricingCalendar)', () => {
    it('loads venues and populates state', async () => {
      const { venues, loadData, error } = usePricingCalendar()
      await loadData()

      expect(api.get).toHaveBeenCalledWith('/admin/venues')
      expect(venues.value).toEqual(mockVenues)
      expect(error.value).toBe('')
    })

    it('surfaces error when venues endpoint fails', async () => {
      vi.mocked(api.get).mockImplementation(async (url: string) => {
        if (url === '/admin/venues') throw new Error('Venues 500')
        return []
      })

      const { loadData, error } = usePricingCalendar()
      await loadData()

      expect(error.value).not.toBe('')
    })
  })

  describe('Caller: Organization Owner (useOrganizationOwner)', () => {
    it('loads same-organization venues from organization details without platform-only venue dependency', async () => {
      vi.mocked(api.get).mockImplementation(async (url: string) => {
        if (url === '/organizations') return { organizations: [{ id: 1, name: 'Org 1' }] }
        if (url.startsWith('/organizations/1')) {
          return {
            organization: {
              id: 1,
              name: 'Org 1',
              venues: mockVenues,
            },
          }
        }
        return []
      })

      const { currentOrg, loadOrgData, error } = useOrganizationOwner()
      await loadOrgData(1)

      expect(api.get).not.toHaveBeenCalledWith('/admin/venues')
      expect(currentOrg.value?.venues?.length).toBe(2)
      expect(error.value).toBe('')
    })
  })

  describe('Caller: Shift Reconciliation (useAdminShiftReconciliation)', () => {
    it('loads venues and populates state', async () => {
      const { venuesList, loadData, error } = useAdminShiftReconciliation()
      await loadData()

      expect(api.get).toHaveBeenCalledWith('/admin/venues')
      expect(venuesList.value).toEqual(mockVenues)
      expect(error.value).toBe('')
    })

    it('surfaces error when venues endpoint fails', async () => {
      vi.mocked(api.get).mockImplementation(async (url: string) => {
        if (url === '/admin/venues') throw new Error('Venues 500')
        return []
      })

      const { loadData, error } = useAdminShiftReconciliation()
      await loadData()

      expect(error.value).not.toBe('')
    })

    it('reports no match rate instead of 100% when no shift has been closed', async () => {
      const { loadData, matchRatePercent } = useAdminShiftReconciliation()
      await loadData()

      expect(matchRatePercent.value).toBeNull()
    })
  })
})

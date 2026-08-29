import { ref, computed } from 'vue'
import { adminApi } from '../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Court, Venue, SportZone } from '@/features/court/types/court'
import type { Refund } from '@/features/booking/types/booking'
import { useAuthStore } from '@/stores/auth'

export type CourtForm = {
  name: string
  venue_id: number
  sport_zone_id: number | null
  description: string
  image_url: string
  is_active: boolean
}

export type Coupon = {
  id: number
  code: string
  discount_type: 'fixed' | 'percent'
  discount_value: number
  usage_count: number
  usage_limit?: number | null
  is_active: boolean
}

export function useAdminManageCourts() {
  const auth = useAuthStore()
  const isDemoAdmin = computed(() => auth.user?.role === 'admin' && auth.user.email?.endsWith('@demo.auracourt.vn'))

  const courts = ref<Court[]>([])
  const venues = ref<Venue[]>([])
  const sportZones = ref<SportZone[]>([])
  const refunds = ref<Refund[]>([])
  const coupons = ref<Coupon[]>([])
  const error = ref('')
  const notice = ref('')
  const saving = ref(false)
  const loading = ref(false)

  // Filter & Search
  const searchQuery = ref('')
  const selectedVenueFilter = ref<number | 'all'>('all')
  const statusFilter = ref<'all' | 'active' | 'inactive'>('all')

  const activeVenues = computed(() => venues.value.filter((venue) => venue.is_active))

  const filteredCourts = computed(() => {
    return courts.value.filter((c) => {
      const matchVenue = selectedVenueFilter.value === 'all' || c.venue?.id === selectedVenueFilter.value
      const matchStatus = statusFilter.value === 'all' || (statusFilter.value === 'active' ? c.is_active !== false : c.is_active === false)
      const matchSearch =
        !searchQuery.value.trim() ||
        c.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        (c.venue?.name || '').toLowerCase().includes(searchQuery.value.toLowerCase())
      return matchVenue && matchStatus && matchSearch
    })
  })

  const totalCourtsCount = computed(() => courts.value.length)
  const activeCourtsCount = computed(() => courts.value.filter((c) => c.is_active !== false).length)
  const totalBlocksCount = computed(() => courts.value.reduce((sum, c) => sum + (c.blocks?.length || 0), 0))
  const pendingRefundsCount = computed(() => refunds.value.length)

  async function load() {
    loading.value = true
    error.value = ''
    try {
      const [loadedCourts, loadedVenues, loadedZones, loadedRefunds, loadedCoupons] = await Promise.all([
        adminApi.getCourts(),
        adminApi.getVenues(),
        adminApi.getSportZones(),
        adminApi.getRefunds<{ data: Refund[] }>(),
        adminApi.getCoupons<{ data: Coupon[] }>(),
      ])
      courts.value = loadedCourts
      venues.value = loadedVenues
      sportZones.value = loadedZones || []
      refunds.value = loadedRefunds.data || []
      coupons.value = loadedCoupons.data || []
    } catch (cause: unknown) {
      error.value = errorMessage(cause, 'Không tải được dữ liệu quản trị.')
    } finally {
      loading.value = false
    }
  }

  async function toggleCourtStatus(court: Court) {
    const nextStatus = court.is_active === false
    if (isDemoAdmin.value) {
      court.is_active = nextStatus
      notice.value = nextStatus ? 'Đã mô phỏng mở lại sân.' : 'Đã mô phỏng ngừng mở sân.'
      return
    }
    try {
      if (nextStatus) {
        await adminApi.updateCourt(court.id, { is_active: true })
        notice.value = `Đã mở lại ${court.name}.`
      } else {
        await adminApi.deleteCourt(court.id)
        notice.value = `Đã ngừng mở ${court.name}.`
      }
      await load()
    } catch (cause: unknown) {
      error.value = errorMessage(cause, 'Không thể thay đổi trạng thái sân.')
    }
  }

  return {
    courts,
    venues,
    sportZones,
    refunds,
    coupons,
    error,
    notice,
    saving,
    loading,
    searchQuery,
    selectedVenueFilter,
    statusFilter,
    activeVenues,
    filteredCourts,
    totalCourtsCount,
    activeCourtsCount,
    totalBlocksCount,
    pendingRefundsCount,
    isDemoAdmin,
    load,
    toggleCourtStatus,
  }
}

import { ref, computed } from 'vue'
import { adminApi } from '../api/adminApi'
import { api } from '@/core/api/client'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Venue, SportZone } from '@/features/court/types/court'

export interface AdminVenueItem extends Venue {
  courts_count?: number
}

export function useAdminVenues() {
  const loading = ref(false)
  const saving = ref(false)
  const notice = ref('')
  const error = ref('')

  const searchQuery = ref('')
  const statusFilter = ref<'all' | 'active' | 'inactive'>('all')

  const venues = ref<AdminVenueItem[]>([])
  const sportZones = ref<SportZone[]>([])

  const showVenueModal = ref(false)
  const editingVenue = ref<AdminVenueItem | null>(null)
  const showQrModal = ref(false)
  const selectedVenueForQr = ref<AdminVenueItem | null>(null)

  const filteredVenues = computed(() => {
    return venues.value.filter((v) => {
      const matchStatus =
        statusFilter.value === 'all' ||
        (statusFilter.value === 'active' && v.is_active) ||
        (statusFilter.value === 'inactive' && !v.is_active)

      const q = searchQuery.value.trim().toLowerCase()
      const matchSearch =
        !q ||
        v.name.toLowerCase().includes(q) ||
        (v.address || '').toLowerCase().includes(q)

      return matchStatus && matchSearch
    })
  })

  const totalVenuesCount = computed(() => venues.value.length)
  const totalCourtsCount = computed(() => {
    return venues.value.reduce((sum, v) => sum + (v.courts_count || 0), 0)
  })

  async function loadData() {
    loading.value = true
    try {
      const [vRes, zRes] = await Promise.all([
        adminApi.getVenues(),
        adminApi.getSportZones(),
      ])
      if (vRes) venues.value = vRes as AdminVenueItem[]
      if (zRes) sportZones.value = zRes
    } catch (e) {
      // fallback
    } finally {
      loading.value = false
    }
  }

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4000)
  }

  async function toggleVenueActive(venue: AdminVenueItem) {
    const nextStatus = !venue.is_active
    try {
      await api.patch(`/admin/venues/${venue.id}`, { is_active: nextStatus })
      venue.is_active = nextStatus
      showNotice(`Đã chuyển cơ sở ${venue.name} sang trạng thái ${nextStatus ? 'Hoạt động' : 'Tạm dừng'}!`)
    } catch (e) {
      error.value = errorMessage(e, 'Không thể cập nhật trạng thái cơ sở.')
    }
  }

  return {
    loading,
    saving,
    notice,
    error,
    searchQuery,
    statusFilter,
    venues,
    sportZones,
    showVenueModal,
    editingVenue,
    showQrModal,
    selectedVenueForQr,
    filteredVenues,
    totalVenuesCount,
    totalCourtsCount,
    loadData,
    showNotice,
    toggleVenueActive,
  }
}

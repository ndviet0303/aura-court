import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { adminApi } from '../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Venue, SportZone } from '@/features/court/types/court'
import type { VenueForm } from '../types/admin'
import type { VenueBookingPolicySettings, VenueBookingPolicyVersion } from '../types/admin'

export interface AdminVenueItem extends Venue {
  organization_id?: number
  courts_count?: number
}

export function useAdminVenues() {
  const { t } = useI18n()

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
  const bookingPolicy = ref<VenueBookingPolicyVersion | null>(null)
  const loadingBookingPolicy = ref(false)
  const savingBookingPolicy = ref(false)
  const showQrModal = ref(false)
  const selectedVenueForQr = ref<AdminVenueItem | null>(null)

  const filteredVenues = computed(() => {
    const q = searchQuery.value.trim().toLowerCase()
    return venues.value.filter((v) => {
      const matchStatus =
        statusFilter.value === 'all' ||
        (statusFilter.value === 'active' && v.is_active) ||
        (statusFilter.value === 'inactive' && !v.is_active)

      const venueName = typeof v.name === 'string' ? v.name : (v.name as any)?.vi || (v.name as any)?.en || ''
      const venueAddr = typeof v.address === 'string' ? v.address : (v.address as any)?.vi || (v.address as any)?.en || ''

      const matchSearch =
        !q ||
        venueName.toLowerCase().includes(q) ||
        venueAddr.toLowerCase().includes(q) ||
        (v.slug && v.slug.toLowerCase().includes(q))

      return matchStatus && matchSearch
    })
  })

  const totalVenuesCount = computed(() => venues.value.length)
  const activeVenuesCount = computed(() => venues.value.filter((v) => v.is_active).length)
  const totalCourtsCount = computed(() => {
    return venues.value.reduce((sum, v) => sum + (v.courts_count || 0), 0)
  })
  const mapsLinkedCount = computed(() => {
    return venues.value.filter((v) => !!v.google_maps_url).length
  })

  function showNotice(msg: string) {
    notice.value = msg
    error.value = ''
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4000)
  }

  function showError(msg: string) {
    error.value = msg
    notice.value = ''
    setTimeout(() => {
      if (error.value === msg) error.value = ''
    }, 6000)
  }

  async function loadData() {
    loading.value = true
    error.value = ''
    try {
      const [vRes, zRes] = await Promise.all([
        adminApi.getVenues(),
        adminApi.getSportZones(),
      ])
      if (vRes) venues.value = vRes as AdminVenueItem[]
      if (zRes) sportZones.value = zRes
    } catch (e) {
      showError(errorMessage(e, t('admin.venues.noVenuesFound')))
    } finally {
      loading.value = false
    }
  }

  async function saveVenue(payload: VenueForm) {
    saving.value = true
    error.value = ''
    try {
      if (editingVenue.value) {
        const updated = await adminApi.updateVenue(editingVenue.value.id, payload)
        if (updated) {
          const index = venues.value.findIndex((v) => v.id === editingVenue.value?.id)
          if (index !== -1) {
            venues.value[index] = { ...venues.value[index], ...updated }
          }
        }
        showNotice(t('admin.venues.updateVenueSuccess', { name: payload.name }))
      } else {
        const created = await adminApi.createVenue(payload)
        if (created) {
          venues.value.unshift(created as AdminVenueItem)
        }
        showNotice(t('admin.venues.createVenueSuccess', { name: payload.name }))
      }
      showVenueModal.value = false
      editingVenue.value = null
    } catch (e) {
      showError(errorMessage(e, 'Không thể lưu thông tin cơ sở'))
      throw e
    } finally {
      saving.value = false
    }
  }

  async function loadBookingPolicy(venue: AdminVenueItem) {
    bookingPolicy.value = null
    if (!venue.organization_id) return

    loadingBookingPolicy.value = true
    try {
      const response = await adminApi.getVenueBookingPolicy(venue.organization_id, venue.id)
      bookingPolicy.value = response.data
    } catch (e) {
      showError(errorMessage(e, 'Không thể tải chính sách đặt sân.'))
    } finally {
      loadingBookingPolicy.value = false
    }
  }

  async function saveBookingPolicy(settings: VenueBookingPolicySettings) {
    const venue = editingVenue.value
    if (!venue?.organization_id) throw new Error('Venue organization is unavailable.')

    savingBookingPolicy.value = true
    try {
      const response = await adminApi.saveVenueBookingPolicy(venue.organization_id, venue.id, settings)
      bookingPolicy.value = response.data
      showNotice(t('admin.venues.bookingPolicySaved', { version: response.data.version }))
    } catch (e) {
      showError(errorMessage(e, 'Không thể lưu chính sách đặt sân.'))
      throw e
    } finally {
      savingBookingPolicy.value = false
    }
  }

  async function toggleVenueActive(venue: AdminVenueItem) {
    const nextStatus = !venue.is_active
    try {
      await adminApi.updateVenue(venue.id, { is_active: nextStatus })
      venue.is_active = nextStatus
      const statusText = nextStatus ? t('admin.venues.statusActive') : t('admin.venues.statusInactive')
      const nameStr = typeof venue.name === 'string' ? venue.name : (venue.name as any)?.vi || (venue.name as any)?.en || ''
      showNotice(t('admin.venues.toggleStatusSuccess', { name: nameStr, status: statusText }))
    } catch (e) {
      showError(errorMessage(e, 'Không thể cập nhật trạng thái cơ sở.'))
    }
  }

  async function deleteVenue(venue: AdminVenueItem) {
    try {
      await adminApi.deleteVenue(venue.id)
      venue.is_active = false
      const nameStr = typeof venue.name === 'string' ? venue.name : (venue.name as any)?.vi || (venue.name as any)?.en || ''
      showNotice(t('admin.venues.deleteVenueSuccess', { name: nameStr }))
    } catch (e) {
      showError(errorMessage(e, 'Không thể ngưng hoạt động cơ sở.'))
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
    bookingPolicy,
    loadingBookingPolicy,
    savingBookingPolicy,
    showQrModal,
    selectedVenueForQr,
    filteredVenues,
    totalVenuesCount,
    activeVenuesCount,
    totalCourtsCount,
    mapsLinkedCount,
    loadData,
    showNotice,
    showError,
    saveVenue,
    loadBookingPolicy,
    saveBookingPolicy,
    toggleVenueActive,
    deleteVenue,
  }
}

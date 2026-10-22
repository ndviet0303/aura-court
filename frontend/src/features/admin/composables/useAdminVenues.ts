import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { adminApi } from '../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type { ApiError } from '@/core/types/api'
import type { OperatingStatus, Venue } from '@/features/court/types/court'
import { useAuthStore } from '@/stores/auth'
import type { VenueForm } from '../types/admin'
import type { VenueBookingPolicySettings, VenueBookingPolicyVersion } from '../types/admin'

export interface AdminVenueItem extends Venue {
  organization_id?: number
  courts_count?: number
}

export function useAdminVenues() {
  const { t } = useI18n()
  const auth = useAuthStore()

  const loading = ref(false)
  const saving = ref(false)
  const notice = ref('')
  const error = ref('')
  // 422 errors of the venue form, keyed by field (usePartnerRegistration pattern).
  const fieldErrors = ref<Record<string, string[]>>({})

  const searchQuery = ref('')
  const statusFilter = ref<'all' | OperatingStatus>('all')

  const venues = ref<AdminVenueItem[]>([])

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
      const matchStatus = statusFilter.value === 'all' || v.status === statusFilter.value

      const venueName =
        typeof v.name === 'string' ? v.name : (v.name as any)?.vi || (v.name as any)?.en || ''
      const venueAddr =
        typeof v.address === 'string'
          ? v.address
          : (v.address as any)?.vi || (v.address as any)?.en || ''

      const matchSearch =
        !q ||
        venueName.toLowerCase().includes(q) ||
        venueAddr.toLowerCase().includes(q) ||
        (v.slug && v.slug.toLowerCase().includes(q))

      return matchStatus && matchSearch
    })
  })

  const totalVenuesCount = computed(() => venues.value.length)
  const activeVenuesCount = computed(
    () => venues.value.filter((v) => v.status === 'active').length,
  )
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
      const vRes = await adminApi.getVenues()
      if (vRes) venues.value = vRes as AdminVenueItem[]
    } catch (e) {
      showError(errorMessage(e, t('admin.venues.noVenuesFound')))
    } finally {
      loading.value = false
    }
  }

  // ponytail: a new venue goes to the active organization when owned, else the first owned one;
  // there is no organization picker for owners of several organizations.
  function ownedOrganizationId(): number | undefined {
    const owned = (auth.user?.organizations ?? [])
      .filter((o) => o.status === 'active' && o.role === 'organization_owner')
      .map((o) => o.organization_id ?? o.id)
      .filter((id): id is number => typeof id === 'number')
    const caps = auth.user?.capabilities
    const active = caps && !Array.isArray(caps) ? caps.active_organization_id : undefined
    return owned.includes(active) ? active : owned[0]
  }

  // Staff list their assigned venues read-only; only an owner gets the add-venue action.
  const canCreateVenue = computed(() => ownedOrganizationId() !== undefined)

  function openVenueForm(venue: AdminVenueItem | null) {
    editingVenue.value = venue
    bookingPolicy.value = null
    fieldErrors.value = {}
    showVenueModal.value = true
    if (venue) void loadBookingPolicy(venue)
  }

  // On failure the modal stays open with the entered values and the errors next to their fields.
  async function saveVenue(payload: VenueForm) {
    const venue = editingVenue.value
    const organizationId = venue ? venue.organization_id : ownedOrganizationId()
    if (!organizationId) {
      showError(t('admin.venues.noOwnerOrganization'))
      return
    }
    saving.value = true
    error.value = ''
    fieldErrors.value = {}
    try {
      if (venue) {
        const { data } = await adminApi.updateVenue(organizationId, venue.id, payload)
        // The response holds the saved values, so the list shows them without a reload.
        const index = venues.value.findIndex((v) => v.id === venue.id)
        if (index !== -1) venues.value[index] = { ...venues.value[index], ...data }
        showNotice(t('admin.venues.updateVenueSuccess', { name: payload.name }))
      } else {
        const { data } = await adminApi.createVenue(organizationId, payload)
        venues.value.unshift(data)
        showNotice(t('admin.venues.createVenueSuccess', { name: payload.name }))
      }
      showVenueModal.value = false
      editingVenue.value = null
    } catch (e) {
      fieldErrors.value = (e as ApiError).errors ?? {}
      showError(errorMessage(e, 'Không thể lưu thông tin cơ sở'))
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
      const response = await adminApi.saveVenueBookingPolicy(
        venue.organization_id,
        venue.id,
        settings,
      )
      bookingPolicy.value = response.data
      showNotice(t('admin.venues.bookingPolicySaved', { version: response.data.version }))
    } catch (e) {
      showError(errorMessage(e, 'Không thể lưu chính sách đặt sân.'))
      throw e
    } finally {
      savingBookingPolicy.value = false
    }
  }

  return {
    loading,
    saving,
    notice,
    error,
    fieldErrors,
    searchQuery,
    statusFilter,
    venues,
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
    canCreateVenue,
    loadData,
    showNotice,
    showError,
    openVenueForm,
    saveVenue,
    loadBookingPolicy,
    saveBookingPolicy,
  }
}

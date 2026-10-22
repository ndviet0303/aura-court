import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { adminApi } from '../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'
import { resolveLocalizedText } from '@/i18n'
import { useAuthStore } from '@/stores/auth'
import { OPERATING_STATUSES } from '@/features/court/types/court'
import type { Court, OperatingStatus, Venue, SportType } from '@/features/court/types/court'
import type { Coupon } from '@/features/booking/types/booking'
export type { Coupon }

export type CourtForm = {
  name: string
  venue_id: number
  // Lives on the venue; edited here so admins never have to pick a venue.
  address: string
  sport_type: SportType
  description: string
  image_url: string
  surface_type: NonNullable<Court['surface_type']> | ''
  capacity: number | null
}

const STATUS_ICONS: Record<OperatingStatus, string> = {
  inactive: 'hourglass_empty',
  active: 'check_circle',
  paused: 'pause_circle',
  closed: 'block',
}

export function useAdminManageCourts() {
  const { t } = useI18n()
  const auth = useAuthStore()

  function activeOrganizationId(): number | undefined {
    const user = auth.user
    if (!user) return undefined
    if (
      user.capabilities &&
      typeof user.capabilities === 'object' &&
      !Array.isArray(user.capabilities)
    ) {
      const activeOrgId = user.capabilities.active_organization_id
      if (typeof activeOrgId === 'number') return activeOrgId
    }
    const firstOrgId = user.organizations?.[0]?.organization_id ?? user.organizations?.[0]?.id
    return typeof firstOrgId === 'number' ? firstOrgId : undefined
  }

  const courts = ref<Court[]>([])
  const venues = ref<Venue[]>([])
  const coupons = ref<Coupon[]>([])
  const error = ref('')
  const notice = ref('')
  const saving = ref(false)
  const loading = ref(false)

  // Filter & Search
  const searchQuery = ref('')
  const selectedVenueFilter = ref<number | 'all'>('all')
  const statusFilter = ref<'all' | OperatingStatus>('all')

  // Modals & Sub-states
  const showCourtModal = ref(false)
  const editingCourtId = ref<number | null>(null)
  const courtForm = ref<CourtForm>({
    name: '',
    venue_id: 0,
    address: '',
    sport_type: 'badminton',
    description: '',
    image_url: '',
    surface_type: '',
    capacity: null,
  })

  const showCouponModal = ref(false)
  const couponSaving = ref(false)

  // A closed venue takes no new courts; inactive ones still do while the owner sets them up.
  const activeVenues = computed(() => venues.value.filter((venue) => venue.status !== 'closed'))

  const filteredCourts = computed(() => {
    return courts.value.filter((c) => {
      const matchVenue =
        selectedVenueFilter.value === 'all' || c.venue?.id === selectedVenueFilter.value
      const matchStatus = statusFilter.value === 'all' || c.status === statusFilter.value
      const matchSearch =
        !searchQuery.value.trim() ||
        resolveLocalizedText(c.name).toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        resolveLocalizedText(c.venue?.name || '')
          .toLowerCase()
          .includes(searchQuery.value.toLowerCase())
      return matchVenue && matchStatus && matchSearch
    })
  })

  const totalCourtsCount = computed(() => courts.value.length)
  const activeCourtsCount = computed(() => courts.value.filter((c) => c.status === 'active').length)
  const pausedCourtsCount = computed(() => courts.value.filter((c) => c.status === 'paused').length)

  const venueFilterOptions = computed(() => [
    {
      value: 'all',
      label: t('admin.manage.allVenuesFilter', { count: venues.value.length }),
      icon: 'domain',
    },
    ...venues.value.map((v) => ({
      value: v.id,
      label: resolveLocalizedText(v.name),
      sublabel: v.address || undefined,
      icon: 'location_on',
    })),
  ])

  const statusFilterOptions = computed(() => [
    { value: 'all', label: t('admin.manage.allStatusFilter'), icon: 'filter_list' },
    ...OPERATING_STATUSES.map((value) => ({
      value,
      label: t(`admin.lifecycle.status.${value}`),
      icon: STATUS_ICONS[value],
    })),
  ])

  function clearAlerts() {
    error.value = ''
    notice.value = ''
  }

  async function load() {
    loading.value = true
    clearAlerts()
    try {
      const [loadedCourts, loadedVenues, loadedCoupons] = await Promise.all([
        adminApi.getCourts(),
        adminApi.getVenues(),
        adminApi.getCoupons<{ data: Coupon[] } | Coupon[]>(activeOrganizationId()),
      ])
      courts.value = loadedCourts || []
      venues.value = loadedVenues || []
      coupons.value = Array.isArray(loadedCoupons)
        ? loadedCoupons
        : (loadedCoupons as { data: Coupon[] })?.data || []
    } catch (cause: unknown) {
      error.value = errorMessage(cause, t('admin.manage.loadDataError'))
    } finally {
      loading.value = false
    }
  }

  // The address the form opened with, so saving only touches the venue when it changed.
  let openedAddress = ''

  function venueAddress(venueId: number) {
    return venues.value.find((venue) => venue.id === venueId)?.address || ''
  }

  function openCreateCourtModal() {
    editingCourtId.value = null
    // New courts go to the venue being filtered on, else the first open one.
    const venueId =
      typeof selectedVenueFilter.value === 'number'
        ? selectedVenueFilter.value
        : activeVenues.value[0]?.id || (venues.value[0]?.id ?? 0)
    openedAddress = venueAddress(venueId)
    courtForm.value = {
      name: '',
      venue_id: venueId,
      address: openedAddress,
      sport_type: 'badminton',
      description: '',
      image_url: '',
      surface_type: '',
      capacity: null,
    }
    showCourtModal.value = true
  }

  function openEditCourtModal(court: Court) {
    editingCourtId.value = court.id
    openedAddress = court.venue?.address || venueAddress(court.venue?.id ?? 0)
    courtForm.value = {
      name: resolveLocalizedText(court.name),
      venue_id: court.venue?.id || activeVenues.value[0]?.id || 0,
      address: openedAddress,
      sport_type: court.sport_type,
      description: resolveLocalizedText(court.description) || '',
      image_url: court.image_url || '',
      surface_type: court.surface_type || '',
      capacity: court.capacity ?? null,
    }
    showCourtModal.value = true
  }

  async function saveCourt() {
    saving.value = true
    clearAlerts()
    try {
      const { address, ...court } = courtForm.value
      const nextAddress = address.trim()
      if (nextAddress && nextAddress !== openedAddress) {
        await adminApi.updateVenue(court.venue_id, { address: nextAddress })
      }
      const payload = {
        ...court,
        image_url: courtForm.value.image_url || null,
        surface_type: courtForm.value.surface_type || null,
        capacity: courtForm.value.capacity ? Number(courtForm.value.capacity) : null,
      }

      if (editingCourtId.value) {
        await adminApi.updateCourt(editingCourtId.value, payload)
        notice.value = t('admin.manage.updateCourtSuccess')
      } else {
        await adminApi.createCourt(payload)
        notice.value = t('admin.manage.createCourtSuccess')
      }
      showCourtModal.value = false
      await load()
    } catch (cause: unknown) {
      error.value = errorMessage(cause, t('admin.manage.saveCourtError'))
    } finally {
      saving.value = false
    }
  }

  function openCouponModal() {
    showCouponModal.value = true
  }

  async function createCoupon(payload: Record<string, unknown>) {
    couponSaving.value = true
    clearAlerts()
    try {
      await adminApi.createCoupon({ ...payload, organization_id: activeOrganizationId() })
      notice.value = t('admin.manage.createCouponSuccess')
      await load()
    } catch (cause: unknown) {
      error.value = errorMessage(cause, t('admin.manage.createCouponError'))
    } finally {
      couponSaving.value = false
    }
  }

  async function updateCoupon(id: number, payload: Record<string, unknown>) {
    couponSaving.value = true
    clearAlerts()
    try {
      await adminApi.updateCoupon(id, { ...payload, organization_id: activeOrganizationId() })
      notice.value = t('admin.manage.updateCouponSuccess')
      await load()
    } catch (cause: unknown) {
      error.value = errorMessage(cause, t('admin.manage.updateCouponError'))
    } finally {
      couponSaving.value = false
    }
  }

  async function transitionCoupon(coupon: Coupon, action: 'activate' | 'suspend' | 'retire') {
    couponSaving.value = true
    clearAlerts()
    try {
      const organizationId = activeOrganizationId()
      if (action === 'activate' && coupon.campaign && coupon.campaign.status !== 'active') {
        await adminApi.transitionPromotionCampaign(coupon.campaign.id, 'activate', organizationId)
      }
      await adminApi.transitionCoupon(coupon.id, action, organizationId)
      await load()
    } catch (cause: unknown) {
      error.value = errorMessage(cause, t('admin.manage.updateCouponError'))
    } finally {
      couponSaving.value = false
    }
  }

  async function transitionCampaign(campaignId: number, action: 'activate' | 'suspend' | 'retire') {
    couponSaving.value = true
    clearAlerts()
    try {
      await adminApi.transitionPromotionCampaign(campaignId, action, activeOrganizationId())
      await load()
    } catch (cause: unknown) {
      error.value = errorMessage(cause, t('admin.manage.updateCouponError'))
    } finally {
      couponSaving.value = false
    }
  }

  return {
    courts,
    venues,
    coupons,
    error,
    notice,
    saving,
    loading,
    searchQuery,
    selectedVenueFilter,
    statusFilter,
    showCourtModal,
    editingCourtId,
    courtForm,
    showCouponModal,
    couponSaving,
    activeVenues,
    filteredCourts,
    totalCourtsCount,
    activeCourtsCount,
    pausedCourtsCount,
    venueFilterOptions,
    statusFilterOptions,
    load,
    openCreateCourtModal,
    openEditCourtModal,
    saveCourt,
    openCouponModal,
    createCoupon,
    updateCoupon,
    transitionCoupon,
    transitionCampaign,
  }
}

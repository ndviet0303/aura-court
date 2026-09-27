import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { adminApi } from '../api/adminApi'
import { refundReviewApi, type RefundObligation } from '../api/refundReviewApi'
import { errorMessage } from '@/core/utils/errorMessage'
import { resolveLocalizedText } from '@/i18n'
import { useAuthStore } from '@/stores/auth'
import type { Court, Venue, SportZone } from '@/features/court/types/court'
import type { Coupon } from '@/features/booking/types/booking'
export type { Coupon }

export type CourtForm = {
  name: string
  venue_id: number
  sport_zone_id: number | null
  description: string
  image_url: string
  surface_type: NonNullable<Court['surface_type']> | ''
  capacity: number | null
  is_active: boolean
}

export function useAdminManageCourts() {
  const { t } = useI18n()
  const auth = useAuthStore()

  function activeOrganizationId(): number | undefined {
    const user = auth.user
    if (!user) return undefined
    if (user.capabilities && typeof user.capabilities === 'object' && !Array.isArray(user.capabilities)) {
      const activeOrgId = user.capabilities.active_organization_id
      if (typeof activeOrgId === 'number') return activeOrgId
    }
    const firstOrgId = user.organizations?.[0]?.organization_id ?? user.organizations?.[0]?.id
    return typeof firstOrgId === 'number' ? firstOrgId : undefined
  }

  const courts = ref<Court[]>([])
  const venues = ref<Venue[]>([])
  const sportZones = ref<SportZone[]>([])
  const refunds = ref<RefundObligation[]>([])
  const coupons = ref<Coupon[]>([])
  const error = ref('')
  const notice = ref('')
  const saving = ref(false)
  const loading = ref(false)

  // Filter & Search
  const searchQuery = ref('')
  const selectedVenueFilter = ref<number | 'all'>('all')
  const statusFilter = ref<'all' | 'active' | 'inactive'>('all')
  const activeTab = ref<'courts' | 'refunds'>('courts')

  // Modals & Sub-states
  const showCourtModal = ref(false)
  const editingCourtId = ref<number | null>(null)
  const courtForm = ref<CourtForm>({
    name: '',
    venue_id: 0,
    sport_zone_id: null,
    description: '',
    image_url: '',
    surface_type: '',
    capacity: null,
    is_active: true,
  })

  const showBlockModal = ref(false)
  const selectedCourtForBlock = ref<Court | null>(null)
  const blockSaving = ref(false)

  const showCouponModal = ref(false)
  const couponSaving = ref(false)

  const activeVenues = computed(() => venues.value.filter((venue) => venue.is_active))

  const filteredCourts = computed(() => {
    return courts.value.filter((c) => {
      const matchVenue = selectedVenueFilter.value === 'all' || c.venue?.id === selectedVenueFilter.value
      const matchStatus =
        statusFilter.value === 'all' ||
        (statusFilter.value === 'active' ? c.is_active !== false : c.is_active === false)
      const matchSearch =
        !searchQuery.value.trim() ||
        resolveLocalizedText(c.name).toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        resolveLocalizedText(c.venue?.name || '').toLowerCase().includes(searchQuery.value.toLowerCase())
      return matchVenue && matchStatus && matchSearch
    })
  })

  const totalCourtsCount = computed(() => courts.value.length)
  const activeCourtsCount = computed(() => courts.value.filter((c) => c.is_active !== false).length)
  const totalBlocksCount = computed(() => courts.value.reduce((sum, c) => sum + (c.blocks?.length || 0), 0))

  const venueFilterOptions = computed(() => [
    { value: 'all', label: t('admin.manage.allVenuesFilter', { count: venues.value.length }), icon: 'domain' },
    ...venues.value.map((v) => ({
      value: v.id,
      label: resolveLocalizedText(v.name),
      sublabel: v.address || undefined,
      icon: 'location_on',
    })),
  ])

  const statusFilterOptions = computed(() => [
    { value: 'all', label: t('admin.manage.allStatusFilter'), icon: 'filter_list' },
    { value: 'active', label: t('admin.manage.statusOpen'), icon: 'check_circle' },
    { value: 'inactive', label: t('admin.manage.statusClosed'), icon: 'pause_circle' },
  ])

  function clearAlerts() {
    error.value = ''
    notice.value = ''
  }

  async function loadRefunds() {
    const orgId = activeOrganizationId()
    if (!orgId) {
      refunds.value = []
      return
    }
    const res = await refundReviewApi.listRefundInbox(orgId)
    refunds.value = res.data || []
  }

  async function load() {
    loading.value = true
    clearAlerts()
    try {
      const [loadedCourts, loadedVenues, loadedZones, loadedCoupons] = await Promise.all([
        adminApi.getCourts(),
        adminApi.getVenues(),
        adminApi.getSportZones(),
        adminApi.getCoupons<{ data: Coupon[] } | Coupon[]>(activeOrganizationId()),
        loadRefunds(),
      ])
      courts.value = loadedCourts || []
      venues.value = loadedVenues || []
      sportZones.value = loadedZones || []
      coupons.value = Array.isArray(loadedCoupons) ? loadedCoupons : (loadedCoupons as { data: Coupon[] })?.data || []
    } catch (cause: unknown) {
      error.value = errorMessage(cause, t('admin.manage.loadDataError'))
    } finally {
      loading.value = false
    }
  }

  async function toggleCourtStatus(court: Court) {
    const nextStatus = court.is_active === false
    clearAlerts()
    try {
      if (nextStatus) {
        await adminApi.updateCourt(court.id, { is_active: true })
        notice.value = t('admin.manage.statusOpenedNotice', { name: resolveLocalizedText(court.name) })
      } else {
        await adminApi.deleteCourt(court.id)
        notice.value = t('admin.manage.statusClosedNotice', { name: resolveLocalizedText(court.name) })
      }
      await load()
    } catch (cause: unknown) {
      error.value = errorMessage(cause, t('admin.manage.statusChangeError'))
    }
  }

  function openCreateCourtModal() {
    editingCourtId.value = null
    courtForm.value = {
      name: '',
      venue_id: activeVenues.value[0]?.id || (venues.value[0]?.id ?? 0),
      sport_zone_id: null,
      description: '',
      image_url: '',
      surface_type: '',
      capacity: null,
      is_active: true,
    }
    showCourtModal.value = true
  }

  function openEditCourtModal(court: Court) {
    editingCourtId.value = court.id
    courtForm.value = {
      name: resolveLocalizedText(court.name),
      venue_id: court.venue?.id || activeVenues.value[0]?.id || 0,
      sport_zone_id: court.sport_zone_id || null,
      description: resolveLocalizedText(court.description) || '',
      image_url: court.image_url || '',
      surface_type: court.surface_type || '',
      capacity: court.capacity ?? null,
      is_active: court.is_active !== false,
    }
    showCourtModal.value = true
  }

  async function saveCourt() {
    saving.value = true
    clearAlerts()
    try {
      const payload = {
        ...courtForm.value,
        sport_zone_id: courtForm.value.sport_zone_id || null,
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

  function openBlockModal(court: Court) {
    selectedCourtForBlock.value = court
    showBlockModal.value = true
  }

  async function addBlock(payload: { starts_at: string; ends_at: string; reason: string }) {
    if (!selectedCourtForBlock.value) return
    blockSaving.value = true
    clearAlerts()
    try {
      await adminApi.createBlock(selectedCourtForBlock.value.id, payload)
      notice.value = t('admin.manage.createBlockSuccess')
      showBlockModal.value = false
      await load()
    } catch (cause: unknown) {
      error.value = errorMessage(cause, t('admin.manage.createBlockError'))
    } finally {
      blockSaving.value = false
    }
  }

  async function deleteBlock(blockId: number) {
    if (!selectedCourtForBlock.value) return
    clearAlerts()
    try {
      await adminApi.deleteBlock(selectedCourtForBlock.value.id, blockId)
      notice.value = t('admin.manage.deleteBlockSuccess')
      await load()
      // Refresh local selectedCourt blocks list
      const refreshed = courts.value.find((c) => c.id === selectedCourtForBlock.value?.id)
      if (refreshed) selectedCourtForBlock.value = refreshed
    } catch (cause: unknown) {
      error.value = errorMessage(cause, t('admin.manage.deleteBlockError'))
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
    activeTab,
    showCourtModal,
    editingCourtId,
    courtForm,
    showBlockModal,
    selectedCourtForBlock,
    blockSaving,
    showCouponModal,
    couponSaving,
    activeVenues,
    filteredCourts,
    totalCourtsCount,
    activeCourtsCount,
    totalBlocksCount,
    venueFilterOptions,
    statusFilterOptions,
    load,
    loadRefunds,
    toggleCourtStatus,
    openCreateCourtModal,
    openEditCourtModal,
    saveCourt,
    openBlockModal,
    addBlock,
    deleteBlock,
    openCouponModal,
    createCoupon,
    updateCoupon,
    transitionCoupon,
    transitionCampaign,
  }
}

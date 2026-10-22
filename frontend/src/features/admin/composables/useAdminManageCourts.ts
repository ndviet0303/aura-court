import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { adminApi } from '../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'
import { resolveLocalizedText } from '@/i18n'
import type { Court, Venue, SportType } from '@/features/court/types/court'

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
  is_active: boolean
}

export function useAdminManageCourts() {
  const { t } = useI18n()

  const courts = ref<Court[]>([])
  const venues = ref<Venue[]>([])
  const error = ref('')
  const notice = ref('')
  const saving = ref(false)
  const loading = ref(false)

  // Filter & Search
  const searchQuery = ref('')
  const selectedVenueFilter = ref<number | 'all'>('all')
  const statusFilter = ref<'all' | 'active' | 'inactive'>('all')

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
    is_active: true,
  })

  const showBlockModal = ref(false)
  const selectedCourtForBlock = ref<Court | null>(null)
  const blockSaving = ref(false)

  const activeVenues = computed(() => venues.value.filter((venue) => venue.is_active))

  const filteredCourts = computed(() => {
    return courts.value.filter((c) => {
      const matchVenue =
        selectedVenueFilter.value === 'all' || c.venue?.id === selectedVenueFilter.value
      const matchStatus =
        statusFilter.value === 'all' ||
        (statusFilter.value === 'active' ? c.is_active !== false : c.is_active === false)
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
  const activeCourtsCount = computed(() => courts.value.filter((c) => c.is_active !== false).length)
  const totalBlocksCount = computed(() =>
    courts.value.reduce((sum, c) => sum + (c.blocks?.length || 0), 0),
  )

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
    { value: 'active', label: t('admin.manage.statusOpen'), icon: 'check_circle' },
    { value: 'inactive', label: t('admin.manage.statusClosed'), icon: 'pause_circle' },
  ])

  function clearAlerts() {
    error.value = ''
    notice.value = ''
  }

  async function load() {
    loading.value = true
    clearAlerts()
    try {
      const [loadedCourts, loadedVenues] = await Promise.all([
        adminApi.getCourts(),
        adminApi.getVenues(),
      ])
      courts.value = loadedCourts || []
      venues.value = loadedVenues || []
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
        notice.value = t('admin.manage.statusOpenedNotice', {
          name: resolveLocalizedText(court.name),
        })
      } else {
        await adminApi.deleteCourt(court.id)
        notice.value = t('admin.manage.statusClosedNotice', {
          name: resolveLocalizedText(court.name),
        })
      }
      await load()
    } catch (cause: unknown) {
      error.value = errorMessage(cause, t('admin.manage.statusChangeError'))
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
      is_active: true,
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
      is_active: court.is_active !== false,
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

  return {
    courts,
    venues,
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
    showBlockModal,
    selectedCourtForBlock,
    blockSaving,
    activeVenues,
    filteredCourts,
    totalCourtsCount,
    activeCourtsCount,
    totalBlocksCount,
    venueFilterOptions,
    statusFilterOptions,
    load,
    toggleCourtStatus,
    openCreateCourtModal,
    openEditCourtModal,
    saveCourt,
    openBlockModal,
    addBlock,
    deleteBlock,
  }
}

import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { adminApi } from '../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'
import { resolveLocalizedText } from '@/i18n'
import { OPERATING_STATUSES } from '@/features/court/types/court'
import type { Court, OperatingStatus, Venue, SportType } from '@/features/court/types/court'
import type { ApiError } from '@/core/types/api'

export type CourtForm = {
  code: string
  // Venue and sport are chosen on create and fixed afterwards (BR-27).
  venue_id: number
  sport_type: SportType
  description: string
  images: string[]
  surface_type: NonNullable<Court['surface_type']> | ''
  capacity: number | null
}

const emptyCourtForm = (venueId: number): CourtForm => ({
  code: '',
  venue_id: venueId,
  sport_type: 'badminton',
  description: '',
  images: [],
  surface_type: '',
  capacity: null,
})

const STATUS_ICONS: Record<OperatingStatus, string> = {
  inactive: 'hourglass_empty',
  active: 'check_circle',
  paused: 'pause_circle',
  closed: 'block',
}

export function useAdminManageCourts() {
  const { t } = useI18n()

  const courts = ref<Court[]>([])
  const venues = ref<Venue[]>([])
  const error = ref('')
  const notice = ref('')
  const saving = ref(false)
  const loading = ref(false)
  // Save errors stay inside the open court form.
  const courtErrors = ref<string[]>([])

  // Filter & Search
  const searchQuery = ref('')
  const selectedVenueFilter = ref<number | 'all'>('all')
  const statusFilter = ref<'all' | OperatingStatus>('all')

  // Modals & Sub-states
  const showCourtModal = ref(false)
  const editingCourtId = ref<number | null>(null)
  const courtForm = ref<CourtForm>(emptyCourtForm(0))

  // A closed venue takes no new courts; inactive ones still do while the owner sets them up.
  const activeVenues = computed(() => venues.value.filter((venue) => venue.status !== 'closed'))
  // Editing shows the court's own venue, read-only, even when it is closed.
  const courtVenueOptions = computed(() =>
    (editingCourtId.value ? venues.value : activeVenues.value).map((venue) => ({
      value: venue.id,
      label: resolveLocalizedText(venue.name),
    })),
  )

  const filteredCourts = computed(() => {
    return courts.value.filter((c) => {
      const matchVenue =
        selectedVenueFilter.value === 'all' || c.venue?.id === selectedVenueFilter.value
      const matchStatus = statusFilter.value === 'all' || c.status === statusFilter.value
      const matchSearch =
        !searchQuery.value.trim() ||
        c.code.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
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

  function openCreateCourtModal() {
    editingCourtId.value = null
    courtErrors.value = []
    // New courts go to the venue being filtered on when it is open, else the first open one.
    const filtered = activeVenues.value.find((venue) => venue.id === selectedVenueFilter.value)
    courtForm.value = emptyCourtForm(filtered?.id ?? activeVenues.value[0]?.id ?? 0)
    showCourtModal.value = true
  }

  function openEditCourtModal(court: Court) {
    editingCourtId.value = court.id
    courtErrors.value = []
    courtForm.value = {
      code: court.code,
      venue_id: court.venue?.id ?? court.venue_id ?? 0,
      sport_type: court.sport_type,
      description: resolveLocalizedText(court.description) || '',
      images: [...(court.images ?? [])],
      surface_type: court.surface_type || '',
      capacity: court.capacity ?? null,
    }
    showCourtModal.value = true
  }

  async function saveCourt() {
    saving.value = true
    clearAlerts()
    courtErrors.value = []
    const form = courtForm.value
    const details = {
      code: form.code.trim(),
      description: form.description.trim() || null,
      images: form.images,
      surface_type: form.surface_type || null,
      capacity: form.capacity ? Number(form.capacity) : null,
    }
    try {
      if (editingCourtId.value) {
        // An update never sends venue or sport: both are fixed after create.
        await adminApi.updateCourt(editingCourtId.value, details)
        notice.value = t('admin.manage.updateCourtSuccess')
      } else {
        await adminApi.createCourt({
          ...details,
          venue_id: form.venue_id,
          sport_type: form.sport_type,
        })
        notice.value = t('admin.manage.createCourtSuccess')
      }
      showCourtModal.value = false
      await load()
    } catch (cause: unknown) {
      const fieldErrors = Object.values((cause as ApiError).errors ?? {}).flat()
      courtErrors.value = fieldErrors.length
        ? fieldErrors
        : [errorMessage(cause, t('admin.manage.saveCourtError'))]
    } finally {
      saving.value = false
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
    courtErrors,
    courtVenueOptions,
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
  }
}

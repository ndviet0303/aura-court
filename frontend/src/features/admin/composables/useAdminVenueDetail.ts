import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Court, Venue } from '@/features/court/types/court'
import { adminApi } from '../api/adminApi'
import type { CourtDetail, OperatingSchedule, ScheduleException } from '../types/venueDetail'

/** Read-only venue detail for the owner and assigned staff; the backend scopes each read. */
export function useAdminVenueDetail(organizationId: number, venueId: number) {
  const { t } = useI18n()
  const loading = ref(true)
  const error = ref('')
  const venue = ref<Venue | null>(null)
  const hours = ref<OperatingSchedule | null>(null)
  const exceptions = ref<ScheduleException[]>([])
  // The admin court list carries each court's open stops, needed by the maintenance resume action.
  const courts = ref<Court[]>([])

  async function load() {
    loading.value = true
    error.value = ''
    try {
      const [venueRes, scheduleRes, exceptionRes, courtRes] = await Promise.all([
        adminApi.getPartnerVenue(organizationId, venueId),
        adminApi.getOperatingSchedule(organizationId, venueId),
        adminApi.getScheduleExceptions(organizationId, venueId),
        adminApi.getCourts(venueId),
      ])
      venue.value = venueRes.data
      hours.value = scheduleRes.data
      exceptions.value = exceptionRes.data
      courts.value = courtRes
    } catch (cause) {
      error.value = errorMessage(cause, t('admin.venueDetail.loadError'))
    } finally {
      loading.value = false
    }
  }

  return { loading, error, venue, hours, exceptions, courts, load }
}

/** Read-only court detail: code, sport, description, images, effective service schedule, status. */
export function useAdminCourtDetail(organizationId: number, venueId: number, courtId: number) {
  const { t } = useI18n()
  const loading = ref(true)
  const error = ref('')
  const court = ref<CourtDetail | null>(null)

  async function load() {
    loading.value = true
    error.value = ''
    try {
      court.value = (await adminApi.getPartnerCourt(organizationId, venueId, courtId)).data
    } catch (cause) {
      error.value = errorMessage(cause, t('admin.venueDetail.courtLoadError'))
    } finally {
      loading.value = false
    }
  }

  return { loading, error, court, load }
}

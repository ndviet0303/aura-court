import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { errorMessage } from '@/core/utils/errorMessage'
import { venueDate } from '@/core/utils/venueTime'
import { adminApi } from '@/features/admin/api/adminApi'
import type { Venue } from '@/features/court/types/court'
import { operationsApi } from '../api/operationsApi'
import type { OperationalReservation, OperationsDay } from '../types/operations'

const REFRESH_MS = 30_000

/**
 * FR-101, FR-104: one venue's day by court and its bookings in use. Both refresh every 30 s, since the in-use flags
 * move with the clock and another operator may check a booking in. The venue list is the owner's venues or the staff
 * member's assigned ones (BR-119).
 */
export function useOperationsBoard() {
  const { t } = useI18n()
  const route = useRoute()
  const router = useRouter()

  const venues = ref<Venue[]>([])
  const venueId = ref<number | null>(Number(route.query.venue) || null)
  const date = ref(venueDate(new Date().toISOString()))
  const day = ref<OperationsDay | null>(null)
  const inUse = ref<OperationalReservation[]>([])
  const loading = ref(false)
  const error = ref('')
  let latest = 0
  let timer: ReturnType<typeof setInterval> | undefined

  const venue = computed(() => venues.value.find((v) => v.id === venueId.value) ?? null)
  const organizationId = computed(() => venue.value?.organization_id ?? null)

  /** quiet: a background refresh keeps the shown data and skips the spinner. */
  async function load(quiet = false) {
    const current = venue.value
    const org = current?.organization_id
    if (!current || !org) return
    const request = ++latest
    if (!quiet) loading.value = true
    try {
      const [schedule, using] = await Promise.all([
        operationsApi.day(org, current.id, date.value),
        operationsApi.inUse(org, current.id),
      ])
      if (request !== latest) return
      day.value = schedule
      inUse.value = using.reservations
      error.value = ''
    } catch (e) {
      if (request === latest) error.value = errorMessage(e, t('operations.loadError'))
    } finally {
      if (request === latest) loading.value = false
    }
  }

  watch([venueId, date], () => {
    day.value = null
    inUse.value = []
    void router.replace({ query: { ...route.query, venue: venueId.value ?? undefined } })
    void load()
  })

  onMounted(async () => {
    try {
      venues.value = await adminApi.getVenues()
    } catch (e) {
      error.value = errorMessage(e, t('operations.loadError'))
      return
    }
    if (!venue.value) venueId.value = venues.value[0]?.id ?? null
    else void load()
    timer = setInterval(() => void load(true), REFRESH_MS)
  })
  onBeforeUnmount(() => clearInterval(timer))

  return { venues, venueId, date, day, inUse, loading, error, organizationId, load }
}

import { computed, onMounted, reactive, ref, watch, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApiCall } from '@/core/composables/useApiCall'
import { errorMessage } from '@/core/utils/errorMessage'
import { adminApi } from '../api/adminApi'
import { suspensionApi } from '../api/suspensionApi'
import type { MonitoredVenueDetail, MonitoringFilters } from '../types/suspension'

/** Task 5.10: read-only cross-organization venue monitoring for the platform admin (D13). */
export function useVenueMonitoring() {
  const { t } = useI18n()
  const filters = reactive<MonitoringFilters>({
    organization_id: '',
    status: '',
    suspended: '',
    keyword: '',
  })
  const organizations = ref<Array<{ id: number; name: string }>>([])
  const { data, loading, error, execute } = useApiCall(suspensionApi.listMonitoredVenues, {
    initialLoading: true,
    defaultErrorMessage: t('admin.monitoring.loadError'),
  })
  const venues = computed(() => data.value?.data ?? [])
  const page = computed(() => data.value?.meta.current_page ?? 1)
  const lastPage = computed(() => data.value?.meta.last_page ?? 1)

  // Each value comes from that field's own input or dropdown options.
  const setFilter = (key: keyof MonitoringFilters, value: string | number) =>
    Object.assign(filters, { [key]: value })

  // useApiCall keeps the message in `error`.
  const load = (target = 1) => execute({ ...filters }, target).catch(() => null)
  watch(filters, () => load())

  onMounted(async () => {
    // The organization filter is optional; the list still works without its options.
    organizations.value = await adminApi
      .getOrganizations<{ organizations?: Array<{ id: number; name: string }> }>()
      .then((response) => response.organizations ?? [])
      .catch(() => [])
    await load()
  })

  return { filters, setFilter, organizations, venues, page, lastPage, loading, error, load }
}

/** Task 5.10: one monitored venue, read-only (D13). */
export function useMonitoredVenue(venueId: Ref<number>) {
  const { t } = useI18n()
  const loading = ref(false)
  const error = ref('')
  const venue = ref<MonitoredVenueDetail | null>(null)

  async function load() {
    loading.value = true
    error.value = ''
    try {
      venue.value = (await suspensionApi.getMonitoredVenue(venueId.value)).data
    } catch (cause: unknown) {
      error.value = errorMessage(cause, t('admin.monitoring.loadError'))
    } finally {
      loading.value = false
    }
  }

  watch(venueId, load, { immediate: true })

  return { loading, error, venue, load }
}

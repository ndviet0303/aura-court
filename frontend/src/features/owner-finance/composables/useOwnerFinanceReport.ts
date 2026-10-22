import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApiCall } from '@/core/composables/useApiCall'
import { ownerFinanceApi } from '../api/ownerFinanceApi'
import type { FinanceMetricKey, OwnerFinanceFilters } from '../types/ownerFinance'

/** The five separate report metrics for the venue, court and period, and the metric whose bookings are listed (D12). */
export function useOwnerFinanceReport(organizationId: number, filters: () => OwnerFinanceFilters) {
  const { t } = useI18n()
  const { data, loading, error, execute } = useApiCall(ownerFinanceApi.getReport, {
    initialLoading: true,
    defaultErrorMessage: t('admin.ownerFinance.report.loadError'),
  })

  const metrics = computed(() => data.value?.data.metrics ?? null)
  const selected = ref<FinanceMetricKey | null>(null)

  // U changes as bookings finish, so every filter change or tab visit reads the report again.
  function query() {
    const { venueId, courtId, from, to } = filters()
    return { venue_id: venueId || undefined, court_id: courtId || undefined, from, to }
  }

  function load() {
    return execute(organizationId, query()).catch(() => null)
  }

  // D14: the same filters as the screen, so the file matches what is shown.
  const exportFile = () => ownerFinanceApi.exportReport(organizationId, query())

  watch(filters, () => load())
  onMounted(() => load())

  return { metrics, selected, loading, error, load, exportFile }
}

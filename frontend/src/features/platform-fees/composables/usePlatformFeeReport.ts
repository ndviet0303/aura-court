import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApiCall } from '@/core/composables/useApiCall'
import { venueDate } from '@/core/utils/venueTime'
import { periodRange } from '@/features/owner-finance/utils/financePeriod'
import { platformFeeApi } from '../api/platformFeeApi'
import type { PlatformFeeReportFilters } from '../types/platformFee'

/** Fees collected in a Vietnam calendar period, split by term and by venue and term (D15). */
export function usePlatformFeeReport() {
  const { t } = useI18n()
  const { data, loading, error, execute } = useApiCall(platformFeeApi.getReport, {
    initialLoading: true,
    defaultErrorMessage: t('admin.platformFees.report.loadError'),
  })

  // Opens on the current Vietnam month; every change replaces the object, so the report reloads once per change.
  const filters = ref<PlatformFeeReportFilters>({
    venueId: 0,
    term: 0,
    kind: 'month',
    ...periodRange('month', venueDate(new Date().toISOString())),
  })
  const report = computed(() => data.value?.data ?? null)

  // useApiCall already keeps the message in `error`; the rethrow has nothing left to tell the view.
  function load() {
    const { venueId, term, from, to } = filters.value
    return execute({
      from,
      to,
      venue_id: venueId || undefined,
      term: term || undefined,
    }).catch(() => null)
  }

  watch(filters, () => load())
  onMounted(() => load())

  return { filters, report, loading, error }
}

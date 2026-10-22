import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApiCall } from '@/core/composables/useApiCall'
import { venueDate } from '@/core/utils/venueTime'
import { periodRange } from '@/features/owner-finance/utils/financePeriod'
import { platformFeeApi } from '../api/platformFeeApi'

/** The platform admin dashboard's fee summary: fees collected this Vietnam month and venues expiring within 7 days (D15). */
export function usePlatformFeeSummary() {
  const { t } = useI18n()
  const defaultErrorMessage = t('admin.platformFees.summary.loadError')
  const report = useApiCall(platformFeeApi.getReport, { initialLoading: true, defaultErrorMessage })
  const venues = useApiCall(platformFeeApi.getVenues, { initialLoading: true, defaultErrorMessage })

  const month = periodRange('month', venueDate(new Date().toISOString()))
  const collected = computed(() => report.data.value?.data.totals.amount ?? null)
  const expiring = computed(() => venues.data.value?.expiring_counts[7] ?? null)

  // Each card shows its own failure; useApiCall already keeps the message.
  onMounted(() => {
    report.execute(month).catch(() => null)
    // expiring_counts ignores the list filters, so one row is enough.
    venues.execute({ per_page: 1 }).catch(() => null)
  })

  return {
    month,
    collected,
    expiring,
    collectedLoading: report.loading,
    collectedError: report.error,
    expiringLoading: venues.loading,
    expiringError: venues.error,
  }
}

import { computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApiCall } from '@/core/composables/useApiCall'
import { ownerFinanceApi } from '../api/ownerFinanceApi'
import type { OwnerFinanceFilters } from '../types/ownerFinance'

/** The owner's received transfers for the venue and period, page by page, each reconciled against its booking (D12). */
export function useOwnerFinanceTransactions(
  organizationId: number,
  filters: () => OwnerFinanceFilters,
) {
  const { t } = useI18n()
  const { data, loading, error, execute } = useApiCall(ownerFinanceApi.getTransactions, {
    initialLoading: true,
    defaultErrorMessage: t('admin.ownerFinance.transactions.loadError'),
  })

  const transactions = computed(() => data.value?.data ?? [])
  const page = computed(() => data.value?.meta.current_page ?? 1)
  const lastPage = computed(() => data.value?.meta.last_page ?? 1)
  const total = computed(() => data.value?.meta.total ?? 0)

  // useApiCall already keeps the message in `error`; the rethrow has nothing left to tell the view.
  function load(target = 1) {
    const { venueId, from, to } = filters()
    return execute(organizationId, {
      venue_id: venueId || undefined,
      from,
      to,
      page: target,
    }).catch(() => null)
  }

  watch(filters, () => load())
  onMounted(() => load())

  return { transactions, page, lastPage, total, loading, error, load }
}

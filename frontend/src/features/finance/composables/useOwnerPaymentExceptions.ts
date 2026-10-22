import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApiCall } from '@/core/composables/useApiCall'
import { financeApi } from '../api/financeApi'

/** The owner's read-only, paginated list of direct-owner payment exceptions (FR-81, UC-4.12). */
export function useOwnerPaymentExceptions() {
  const { t } = useI18n()
  const { data, loading, error, execute } = useApiCall(financeApi.getOwnerPaymentExceptions, {
    initialLoading: true,
    defaultErrorMessage: t('admin.paymentExceptions.loadError'),
  })

  const exceptions = computed(() => data.value?.data ?? [])
  const page = computed(() => data.value?.meta.current_page ?? 1)
  const lastPage = computed(() => data.value?.meta.last_page ?? 1)

  // useApiCall already keeps the message in `error`; the rethrow has nothing left to tell the view.
  function load(target = 1) {
    return execute(target).catch(() => null)
  }

  onMounted(() => load())

  return { exceptions, page, lastPage, loading, error, load }
}

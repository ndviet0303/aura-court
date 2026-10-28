import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApiCall } from '@/core/composables/useApiCall'
import { financeApi } from '../api/financeApi'

/** One payment exception opened from the owner's list, with the booking it named (UC-4.12 steps 4-5). */
export function useOwnerPaymentExceptionDetail() {
  const { t } = useI18n()
  const openId = ref<number | null>(null)
  const { data, loading, error, execute, reset } = useApiCall(financeApi.getOwnerPaymentException, {
    defaultErrorMessage: t('admin.paymentExceptions.detail.loadError'),
  })

  const detail = computed(() => data.value?.data ?? null)

  // useApiCall keeps the message in `error`; E-2 lets the owner close the dialog and go back to the list.
  function open(id: number) {
    openId.value = id
    return execute(id).catch(() => null)
  }

  function close() {
    openId.value = null
    reset()
  }

  return { openId, detail, loading, error, open, close }
}

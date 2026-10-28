import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApiCall } from '@/core/composables/useApiCall'
import type { ApiError } from '@/core/types/api'
import { errorMessage } from '@/core/utils/errorMessage'
import { platformFeeApi } from '../api/platformFeeApi'
import type { SchedulePackagePricePayload } from '../types/platformFee'

type FieldErrors = Partial<Record<keyof SchedulePackagePricePayload, string[]>>

/** The four package terms with their applicable and next scheduled prices, and scheduling a price (D3). */
export function usePackagePrices() {
  const { t } = useI18n()
  const { data, loading, error, execute } = useApiCall(platformFeeApi.getPrices, {
    initialLoading: true,
    defaultErrorMessage: t('admin.platformFees.prices.loadError'),
  })
  const terms = computed(() => data.value?.data ?? [])
  const saving = ref(false)
  const notice = ref('')
  const saveError = ref('')
  const fieldErrors = ref<FieldErrors>({})

  // useApiCall already keeps the message in `error`; the rethrow has nothing left to tell the view.
  function load() {
    return execute().catch(() => null)
  }

  async function schedule(payload: SchedulePackagePricePayload): Promise<boolean> {
    saving.value = true
    notice.value = ''
    saveError.value = ''
    fieldErrors.value = {}
    try {
      const saved = await platformFeeApi.schedulePrice(payload)
      // UC-5.3 E-3: the price is saved either way; a failed notice to partners is shown beside it.
      notice.value = t(
        saved.notification_failed
          ? 'admin.platformFees.prices.savedWithoutNotice'
          : 'admin.platformFees.prices.saved',
      )
      if (saved.notification_failed) saveError.value = t('admin.platformFees.prices.notifyFailed')
      await load()
      return true
    } catch (e: unknown) {
      fieldErrors.value = (e as ApiError | null)?.errors ?? {}
      saveError.value = errorMessage(e, t('admin.platformFees.prices.saveError'))
      // UC-5.2 E-1: the term was priced meanwhile; reloading turns the form into an update.
      if ((e as ApiError | null)?.code === 'PACKAGE_PRICE_EXISTS') await load()
      return false
    } finally {
      saving.value = false
    }
  }

  onMounted(() => load())

  return { terms, loading, error, saving, notice, saveError, fieldErrors, load, schedule }
}

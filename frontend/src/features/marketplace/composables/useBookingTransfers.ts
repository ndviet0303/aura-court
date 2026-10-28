import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { transferApi } from '../api/transferApi'
import type { BookingTransfer } from '../types/transfer'

/** UC-9.8: the transactions where I am the seller or the buyer. */
export function useBookingTransfers() {
  const { t } = useI18n()
  const transfers = ref<BookingTransfer[]>([])
  const loading = ref(false)
  const error = ref('')

  onMounted(async () => {
    loading.value = true
    try {
      transfers.value = (await transferApi.transfers()).data
    } catch (e) {
      error.value = errorMessage(e, t('resale.transfers.loadError'))
    } finally {
      loading.value = false
    }
  })

  return { transfers, loading, error }
}

import { ref, watch, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { transferApi } from '../api/transferApi'
import type { BookingTransfer } from '../types/transfer'
import { itemRefusals } from '../utils/transfer'

/**
 * UC-9.8, UC-9.9: one transaction. Only the seller confirms (BR-186); a repeated confirmation returns the
 * stored result (BR-189). Either side cancels while it is pending (D9).
 */
export function useBookingTransfer(id: Ref<number>) {
  const { t } = useI18n()
  const transfer = ref<BookingTransfer | null>(null)
  const refusals = ref<Record<number, string>>({})
  const loading = ref(false)
  const busy = ref(false)
  const error = ref('')
  const notice = ref('')

  async function load() {
    loading.value = true
    error.value = ''
    try {
      transfer.value = (await transferApi.transfer(id.value)).transfer
    } catch (e) {
      error.value = errorMessage(e, t('resale.transfers.loadError'))
    } finally {
      loading.value = false
    }
  }

  async function act(call: (id: number) => Promise<{ transfer: BookingTransfer }>, done: string) {
    busy.value = true
    error.value = ''
    notice.value = ''
    refusals.value = {}
    try {
      transfer.value = (await call(id.value)).transfer
      notice.value = done
      return true
    } catch (e) {
      const message = errorMessage(e, t('resale.transfer.actionError'))
      const reasons = itemRefusals(e)
      // Reload so each booking shows its re-checked reason and the current status.
      await load()
      refusals.value = reasons
      error.value = message
      return false
    } finally {
      busy.value = false
    }
  }

  const confirm = () => act(transferApi.confirm, t('resale.transfer.confirmed'))

  async function cancel() {
    if (!window.confirm(t('resale.transfer.cancelConfirm'))) return
    await act(transferApi.cancel, t('resale.transfer.cancelled'))
  }

  watch(id, () => void load(), { immediate: true })

  return { transfer, refusals, loading, busy, error, notice, confirm, cancel }
}

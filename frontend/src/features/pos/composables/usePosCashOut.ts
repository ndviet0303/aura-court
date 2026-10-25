import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { posApi } from '../api/posApi'
import type { VenueCreditCashOut } from '../types/pos'

/** Counter cash-out of venue credit (design D7): look the customer's code up, hand over the cash, confirm. */
export function usePosCashOut(shiftId: () => number | undefined) {
  const { t } = useI18n()
  const code = ref('')
  const found = ref<VenueCreditCashOut | null>(null)
  const done = ref<(VenueCreditCashOut & { balance: number }) | null>(null)
  const busy = ref(false)
  const error = ref('')
  // One key per looked-up code, so a repeated confirm can never pay out twice.
  let key = ''

  async function run(action: (shift: number, value: string) => Promise<void>) {
    const shift = shiftId()
    const value = code.value.trim().toUpperCase()
    if (!shift || !value || busy.value) return
    busy.value = true
    error.value = ''
    try {
      await action(shift, value)
    } catch (e) {
      error.value = errorMessage(e, t('admin.pos.cashOutFailed'))
    } finally {
      busy.value = false
    }
  }

  const lookup = () =>
    run(async (shift, value) => {
      found.value = null
      found.value = await posApi.lookupCashOut(shift, value)
      key = `pos-cash-out-${shift}-${value}-${Date.now()}`
    })

  const confirm = () =>
    run(async (shift, value) => {
      done.value = await posApi.confirmCashOut(shift, value, key)
      found.value = null
    })

  function reset() {
    code.value = ''
    found.value = null
    done.value = null
    error.value = ''
  }

  return { code, found, done, busy, error, lookup, confirm, reset }
}

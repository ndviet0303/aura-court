import { computed, ref, watch, type Ref, type UnwrapNestedRefs } from 'vue'
import { posApi } from '../api/posApi'
import type { CustomerProfile, PosPaymentMethod, PosTender } from '../types/pos'
type Order = Parameters<typeof posApi.getQuote>[0]

/**
 * Venue credit as a POS tender (design D6): alone when it covers the order, otherwise with cash or
 * QR for the rest. Only an identified customer's balance at this organization can be spent.
 */
export function usePosVenueCredit(
  customer: Ref<CustomerProfile | null>,
  method: Ref<PosPaymentMethod>,
  total: Ref<number>,
) {
  const balance = ref(0)
  const amount = ref(0)
  const rest = ref<'cash' | 'qr'>('cash')
  const part = computed(() => Math.max(0, Math.min(amount.value, balance.value, total.value)))

  watch(
    () => customer.value?.id,
    async (id) => {
      balance.value = 0
      if (method.value === 'credit') method.value = 'cash'
      if (!id) return
      const res = await posApi.getCustomerCredit(id).catch(() => null)
      if (res && customer.value?.id === id) balance.value = res.balance
    },
    { immediate: true },
  )
  // Choosing the credit tender takes as much of the order as the balance covers.
  watch(method, (next) => {
    if (next === 'credit') amount.value = Math.min(balance.value, total.value)
  })

  const setAmount = (value: number) => (amount.value = Math.max(0, Math.floor(value || 0)))
  const setRest = (value: 'cash' | 'qr') => (rest.value = value)

  /** completeOrder's payment fields. A partial credit prices the order on the server first, since the parts must add up to its total. */
  async function payment(order: Order, cashTendered: number) {
    if (method.value !== 'credit') {
      const plain = method.value as 'cash' | 'qr' | 'wallet'
      return { payment_method: plain, cash_tendered: plain === 'cash' ? cashTendered : undefined }
    }
    if (part.value >= total.value) return { payment_method: 'credit' as const }
    const quote = await posApi.getQuote(order)
    const serverTotal = Number(quote?.grand_total ?? total.value)
    const credit = Math.min(part.value, serverTotal)
    if (credit >= serverTotal) return { payment_method: 'credit' as const }
    const tenders: PosTender[] = [
      { method: 'credit', amount: credit },
      { method: rest.value, amount: serverTotal - credit },
    ]
    return { payment_method: 'mixed' as const, allow_mixed_tender: true, tenders }
  }

  return { balance, amount, part, rest, setAmount, setRest, payment }
}

// What the cart panel reads: the composable wrapped in reactive() by PosView.
export type PosVenueCredit = UnwrapNestedRefs<ReturnType<typeof usePosVenueCredit>>

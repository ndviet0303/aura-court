import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { errorMessage } from '@/core/utils/errorMessage'
import { transferApi } from '../api/transferApi'
import type { TransferListing } from '../types/transfer'
import { itemRefusals, priceProblem } from '../utils/transfer'

export type CreateTransferContext = {
  listingId: number
  /** The chat the buyer contacted from; picks that buyer and the bookings they asked about. */
  conversationId?: number | null
  reservationIds?: number[]
}

/**
 * UC-9.7: the seller names the buyer, the bookings and the agreed price of each (capped at what was paid,
 * BR-181). The server re-checks every booking and holds it for that buyer (BR-183, BR-184).
 */
export function useCreateTransfer(context: CreateTransferContext) {
  const { t } = useI18n()
  const router = useRouter()
  const listing = ref<TransferListing | null>(null)
  const buyerId = ref<number | null>(null)
  const selected = ref<number[]>([])
  const prices = reactive<Record<number, number | null>>({})
  const refusals = ref<Record<number, string>>({})
  const loading = ref(false)
  const submitting = ref(false)
  const error = ref('')

  const contacts = computed(() => listing.value?.contacts ?? [])
  const available = computed(
    () => listing.value?.items.filter((item) => item.state === 'available') ?? [],
  )
  const chosen = computed(() =>
    available.value.filter((i) => selected.value.includes(i.reservation_id)),
  )
  const problems = computed(() =>
    Object.fromEntries(
      chosen.value.flatMap((item) => {
        const problem = priceProblem(prices[item.reservation_id], item.paid_amount)
        return problem ? [[item.reservation_id, problem]] : []
      }),
    ),
  )
  const total = computed(() =>
    chosen.value.reduce((sum, item) => sum + (prices[item.reservation_id] ?? 0), 0),
  )
  const canSubmit = computed(
    () =>
      !!buyerId.value &&
      chosen.value.length > 0 &&
      !Object.keys(problems.value).length &&
      !submitting.value,
  )

  function toggle(reservationId: number) {
    selected.value = selected.value.includes(reservationId)
      ? selected.value.filter((rid) => rid !== reservationId)
      : [...selected.value, reservationId]
  }

  function pickBuyer(id: number | null) {
    buyerId.value = id
    const asked = contacts.value.find((c) => c.user.id === id)?.reservation_ids
    if (asked)
      selected.value = available.value.flatMap((i) =>
        asked.includes(i.reservation_id) ? [i.reservation_id] : [],
      )
  }

  async function load() {
    loading.value = true
    try {
      const data = (await transferApi.listing(context.listingId)).listing
      listing.value = data
      if (!data.is_mine) {
        error.value = t('resale.create.notMine')
        return
      }
      data.items.forEach((item) => (prices[item.reservation_id] = item.price))
      const contact = context.conversationId
        ? contacts.value.find((c) => c.conversation_id === context.conversationId)
        : null
      if (contact) pickBuyer(contact.user.id)
      if (context.reservationIds?.length) {
        const open = new Set(available.value.map((i) => i.reservation_id))
        selected.value = context.reservationIds.filter((id) => open.has(id))
      }
    } catch (e) {
      error.value = errorMessage(e, t('resale.detail.loadError'))
    } finally {
      loading.value = false
    }
  }

  async function submit() {
    if (!canSubmit.value || !buyerId.value) return
    submitting.value = true
    error.value = ''
    refusals.value = {}
    try {
      const transfer = (
        await transferApi.create({
          listing_id: context.listingId,
          buyer_id: buyerId.value,
          items: chosen.value.map((item) => ({
            reservation_id: item.reservation_id,
            price: prices[item.reservation_id] ?? 0,
          })),
        })
      ).transfer
      await router.push({ name: 'resale-transfer', params: { id: transfer.id } })
    } catch (e) {
      refusals.value = itemRefusals(e)
      error.value = errorMessage(e, t('resale.create.error'))
    } finally {
      submitting.value = false
    }
  }

  onMounted(load)

  return {
    listing,
    contacts,
    available,
    buyerId,
    selected,
    prices,
    refusals,
    problems,
    total,
    loading,
    submitting,
    error,
    canSubmit,
    toggle,
    pickBuyer,
    submit,
  }
}

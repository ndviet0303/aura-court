import { computed, ref, watch, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { errorMessage } from '@/core/utils/errorMessage'
import { transferApi } from '../api/transferApi'
import type { TransferListing } from '../types/transfer'
import { itemRefusals } from '../utils/transfer'

/**
 * UC-9.5, UC-9.6: one listing with the state of each booking. A buyer picks available bookings and
 * contacts the seller (nothing is held, BR-182); the seller withdraws bookings no transaction holds (D9).
 */
export function useTransferListing(id: Ref<number>) {
  const { t } = useI18n()
  const router = useRouter()
  const listing = ref<TransferListing | null>(null)
  const selected = ref<number[]>([])
  const refusals = ref<Record<number, string>>({})
  const loading = ref(false)
  const busy = ref(false)
  const error = ref('')
  const notice = ref('')

  const available = computed(
    () => listing.value?.items.filter((item) => item.state === 'available') ?? [],
  )

  async function load() {
    loading.value = true
    error.value = ''
    try {
      listing.value = (await transferApi.listing(id.value)).listing
      const open = new Set(available.value.map((item) => item.reservation_id))
      selected.value = selected.value.filter((rid) => open.has(rid))
    } catch (e) {
      error.value = errorMessage(e, t('resale.detail.loadError'))
    } finally {
      loading.value = false
    }
  }

  function toggle(reservationId: number) {
    selected.value = selected.value.includes(reservationId)
      ? selected.value.filter((rid) => rid !== reservationId)
      : [...selected.value, reservationId]
  }

  async function run(action: () => Promise<void>, fallback: string) {
    busy.value = true
    error.value = ''
    notice.value = ''
    refusals.value = {}
    try {
      await action()
    } catch (e) {
      refusals.value = itemRefusals(e)
      error.value = errorMessage(e, fallback)
    } finally {
      busy.value = false
    }
  }

  const contact = () =>
    run(async () => {
      const { conversation_id } = await transferApi.contact(id.value, selected.value)
      await router.push({ path: '/messages', query: { conversation: conversation_id } })
    }, t('resale.detail.contactError'))

  async function withdraw() {
    const count = selected.value.length
    if (!count || !window.confirm(t('resale.detail.withdrawConfirm', { count }))) return
    await run(async () => {
      listing.value = (await transferApi.withdraw(id.value, selected.value)).listing
      selected.value = []
      notice.value = t('resale.detail.withdrawn')
    }, t('resale.detail.withdrawError'))
  }

  watch(
    id,
    () => {
      selected.value = []
      void load()
    },
    { immediate: true },
  )

  return {
    listing,
    available,
    selected,
    refusals,
    loading,
    busy,
    error,
    notice,
    load,
    toggle,
    contact,
    withdraw,
  }
}

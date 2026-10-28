import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { errorMessage } from '@/core/utils/errorMessage'
import { transferApi } from '../api/transferApi'
import type { TransferCandidate } from '../types/transfer'
import { itemRefusals, priceProblem, remainingInGroup } from '../utils/transfer'

export const NOTE_MAX = 1000

/**
 * UC-9.1, UC-9.2: pick upcoming bookings (each with the reason it cannot be listed, FR-139), price each one
 * up to what was paid (BR-181) and post one listing. A refused booking fails the whole post (Bước 2).
 */
export function useTransferListingForm() {
  const { t } = useI18n()
  const route = useRoute()
  const router = useRouter()
  const candidates = ref<TransferCandidate[]>([])
  const selected = ref<number[]>([])
  const prices = reactive<Record<number, number | null>>({})
  const refusals = ref<Record<number, string>>({})
  const note = ref('')
  const loading = ref(false)
  const submitting = ref(false)
  const error = ref('')

  const anchorId = Number(route.query.reservation) || null
  const byId = (id: number) => candidates.value.find((c) => c.reservation_id === id)
  const chosen = computed(() => selected.value.flatMap((id) => byId(id) ?? []))

  // The recurring submission of the booking the customer came from, else of the first chosen one.
  const group = computed(
    () =>
      (anchorId ? byId(anchorId)?.recurring_group : null) ??
      chosen.value.find((c) => c.recurring_group)?.recurring_group ??
      null,
  )
  const groupRemaining = computed(() =>
    group.value ? remainingInGroup(candidates.value, group.value) : [],
  )

  const problems = computed(() =>
    Object.fromEntries(
      chosen.value.flatMap((c) => {
        const problem = priceProblem(prices[c.reservation_id], c.max_price)
        return problem ? [[c.reservation_id, problem]] : []
      }),
    ),
  )
  const total = computed(() =>
    chosen.value.reduce((sum, c) => sum + (prices[c.reservation_id] ?? 0), 0),
  )
  const canSubmit = computed(
    () => chosen.value.length > 0 && !Object.keys(problems.value).length && !submitting.value,
  )

  function select(id: number, on: boolean) {
    const candidate = byId(id)
    if (!candidate?.eligible) return
    if (on && !selected.value.includes(id)) {
      selected.value = [...selected.value, id]
      prices[id] ??= candidate.max_price
    } else if (!on) {
      selected.value = selected.value.filter((rid) => rid !== id)
    }
  }

  const selectGroup = () => groupRemaining.value.forEach((id) => select(id, true))

  async function load() {
    loading.value = true
    try {
      candidates.value = (await transferApi.candidates()).data
      if (anchorId) select(anchorId, true)
    } catch (e) {
      error.value = errorMessage(e, t('resale.form.loadError'))
    } finally {
      loading.value = false
    }
  }

  async function submit() {
    if (!canSubmit.value) return
    submitting.value = true
    error.value = ''
    refusals.value = {}
    try {
      const items = chosen.value.map((c) => ({
        reservation_id: c.reservation_id,
        price: prices[c.reservation_id] ?? 0,
      }))
      const listing = (await transferApi.createListing(note.value.trim(), items)).listing
      await router.push({ name: 'resale-listing', params: { id: listing.id } })
    } catch (e) {
      refusals.value = itemRefusals(e)
      error.value = errorMessage(e, t('resale.form.submitError'))
    } finally {
      submitting.value = false
    }
  }

  onMounted(load)

  return {
    candidates,
    selected,
    prices,
    refusals,
    problems,
    note,
    total,
    groupRemaining,
    loading,
    submitting,
    error,
    canSubmit,
    select,
    selectGroup,
    submit,
  }
}

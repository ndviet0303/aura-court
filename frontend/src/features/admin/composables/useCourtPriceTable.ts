import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ApiError } from '@/core/types/api'
import { errorMessage } from '@/core/utils/errorMessage'
import { venueInstant } from '@/core/utils/venueTime'
import { priceTableApi } from '../api/priceTableApi'
import type {
  PriceCoverageMissingItem,
  PriceTable,
  PriceTableDetail,
  PriceTableKind,
} from '../types/priceTable'
import { usePriceTableLabels } from './usePriceTableLabels'

type PriceTableError = ApiError & { missing_items?: PriceCoverageMissingItem[] }

// An empty switch time means now.
const switchInstant = (local: string) => (local ? venueInstant(local) : undefined)

/**
 * One court's price tables on the court form (design D2, D3): its links, the prices in effect, coverage gaps,
 * and applying or ending a table of the same venue. Prices are never entered here, only picked by table.
 * The panel mounts per opened court, so the ids are plain values.
 */
export function useCourtPriceTable(courtId: number, venueId: number) {
  const { t } = useI18n()
  const labels = usePriceTableLabels()

  const tables = ref<PriceTable[]>([])
  const inEffect = ref<PriceTableDetail[]>([])
  const missing = ref<PriceCoverageMissingItem[] | null>(null)
  const loading = ref(false)
  const saving = ref(false)
  const notice = ref('')
  const errors = ref<string[]>([])
  // Gaps of a 422 PRICE_COVERAGE_INCOMPLETE: nothing was saved.
  const rejectedGaps = ref<string[]>([])
  const tableId = ref<number | null>(null)
  const switchAt = ref('')

  // Links in effect now or scheduled later, as the table list returns them.
  const links = computed(() =>
    tables.value.flatMap((table) =>
      table.links.filter((link) => link.court_id === courtId).map((link) => ({ ...link, table })),
    ),
  )
  const tableOptions = computed(() =>
    tables.value
      .filter((table) => table.status === 'active')
      .map((table) => ({ value: table.id, label: `${table.name} · ${labels.kind(table.kind)}` })),
  )
  // Versions are newest first; prices resolve to the first one already effective.
  const prices = computed(() =>
    inEffect.value.map((table) => ({
      table,
      intervals:
        table.versions.find((version) => Date.parse(version.effective_at) <= Date.now())
          ?.intervals ?? [],
    })),
  )

  async function load() {
    loading.value = true
    try {
      const [list, coverage] = await Promise.all([
        priceTableApi.list(venueId),
        priceTableApi.coverage(courtId),
      ])
      tables.value = list.data
      missing.value = coverage.data.missing_items
      const current = links.value.filter((link) => Date.parse(link.starts_at) <= Date.now())
      inEffect.value = (
        await Promise.all(current.map((link) => priceTableApi.show(link.table.id)))
      ).map((response) => response.data)
    } catch (cause: unknown) {
      errors.value = [errorMessage(cause, t('admin.priceTables.court.loadError'))]
    } finally {
      loading.value = false
    }
  }

  async function change(request: () => Promise<unknown>, success: string) {
    saving.value = true
    notice.value = ''
    errors.value = []
    rejectedGaps.value = []
    try {
      await request()
      notice.value = success
      tableId.value = null
      switchAt.value = ''
      await load()
    } catch (cause: unknown) {
      const error = cause as PriceTableError
      if (error.code === 'PRICE_COVERAGE_INCOMPLETE') {
        rejectedGaps.value = (error.missing_items ?? []).map(labels.missingItem)
      } else {
        const fieldErrors = Object.values(error.errors ?? {}).flat()
        errors.value = fieldErrors.length
          ? fieldErrors
          : [errorMessage(cause, t('admin.priceTables.court.changeError'))]
      }
    } finally {
      saving.value = false
    }
  }

  function apply() {
    const id = tableId.value
    if (!id) return
    const payload = { court_ids: [courtId], starts_at: switchInstant(switchAt.value) }
    return change(() => priceTableApi.applyLinks(id, payload), t('admin.priceTables.appliedNotice'))
  }

  const end = (kind: PriceTableKind) =>
    change(
      () => priceTableApi.endLink(courtId, { kind, ends_at: switchInstant(switchAt.value) }),
      t('admin.priceTables.endedNotice'),
    )

  return {
    labels,
    links,
    prices,
    missing,
    tableOptions,
    tableId,
    switchAt,
    loading,
    saving,
    notice,
    errors,
    rejectedGaps,
    load,
    apply,
    end,
  }
}

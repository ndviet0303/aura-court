import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useErrorModal } from '@/core/composables/useErrorModal'
import type { ApiError } from '@/core/types/api'
import { venueInstant } from '@/core/utils/venueTime'
import type { Court, Venue } from '@/features/court/types/court'
import { adminApi } from '../api/adminApi'
import { priceTableApi } from '../api/priceTableApi'
import type {
  CreatePriceTablePayload,
  CreatePriceTableVersionPayload,
  PriceCoverageMissingItem,
  PriceTable,
  PriceTableDetail,
  PriceTableLink,
} from '../types/priceTable'
import { usePriceTableLabels } from './usePriceTableLabels'

type PriceTableError = ApiError & { missing_items?: PriceCoverageMissingItem[] }

// An empty switch time means now.
const switchInstant = (local: string) => (local ? venueInstant(local) : undefined)

/** Owner price table management of one venue at a time. */
export function usePriceTables() {
  const { t } = useI18n()
  const labels = usePriceTableLabels()
  const errorDialog = useErrorModal()

  const venues = ref<Venue[]>([])
  const venueId = ref<number | null>(null)
  const courts = ref<Court[]>([])
  const tables = ref<PriceTable[]>([])
  const detail = ref<PriceTableDetail | null>(null)
  const loading = ref(false)
  const saving = ref(false)
  const notice = ref('')
  const fieldErrors = ref<Record<string, string[]>>({})

  async function loadVenues() {
    loading.value = true
    try {
      venues.value = await adminApi.getVenues()
      venueId.value = venues.value[0]?.id ?? null
    } catch (e) {
      errorDialog.showError(e)
    } finally {
      loading.value = false
    }
  }

  async function loadVenue() {
    const id = venueId.value
    detail.value = null
    tables.value = []
    courts.value = []
    if (!id) return
    loading.value = true
    try {
      const [list, venueCourts] = await Promise.all([
        priceTableApi.list(id),
        adminApi.getCourts(id),
      ])
      if (id !== venueId.value) return
      tables.value = list.data
      courts.value = venueCourts
    } catch (e) {
      errorDialog.showError(e)
    } finally {
      loading.value = false
    }
  }
  watch(venueId, loadVenue)

  async function openDetail(tableId: number) {
    try {
      detail.value = (await priceTableApi.show(tableId)).data
    } catch (e) {
      errorDialog.showError(e)
    }
  }

  async function refreshList(venue: number) {
    tables.value = (await priceTableApi.list(venue)).data
  }

  // 422 field errors stay inline on the open form; anything else, coverage gaps included, opens the dialog.
  async function mutate(request: () => Promise<void>, success: string): Promise<boolean> {
    saving.value = true
    fieldErrors.value = {}
    notice.value = ''
    try {
      await request()
      notice.value = success
      return true
    } catch (e) {
      const errors = (e as PriceTableError).errors
      if (errors) fieldErrors.value = errors
      else errorDialog.showError(e)
      return false
    } finally {
      saving.value = false
    }
  }

  function create(payload: CreatePriceTablePayload) {
    const venue = venueId.value
    if (!venue) return Promise.resolve(false)
    return mutate(
      async () => {
        detail.value = (await priceTableApi.create(venue, payload)).data
        await refreshList(venue)
      },
      t('admin.priceTables.createdNotice', { name: payload.name }),
    )
  }

  // Changes the open table and reloads the venue's list, whose other tables' links may have moved too.
  function changeDetail(
    change: (table: PriceTableDetail) => Promise<PriceTableDetail>,
    success: string,
  ) {
    const table = detail.value
    if (!table) return Promise.resolve(false)
    return mutate(async () => {
      detail.value = await change(table)
      await refreshList(table.venue_id)
    }, success)
  }

  const createVersion = (payload: CreatePriceTableVersionPayload) =>
    changeDetail(
      async (table) => (await priceTableApi.createVersion(table.id, payload)).data,
      t('admin.priceTables.versionCreatedNotice'),
    )

  const applyLinks = (courtIds: number[], switchAt: string) =>
    changeDetail(async (table) => {
      const payload = { court_ids: courtIds, starts_at: switchInstant(switchAt) }
      return (await priceTableApi.applyLinks(table.id, payload)).data
    }, t('admin.priceTables.appliedNotice'))

  // The end call cuts whichever same-kind link is in effect at the time, so it must stay inside the clicked link's
  // period; left empty on a link not started yet, it cancels that link from its start.
  function endLink(link: PriceTableLink, endsAt: string) {
    const start = Date.parse(link.starts_at)
    const at = endsAt ? venueInstant(endsAt) : start > Date.now() ? link.starts_at : undefined
    if (
      at !== undefined &&
      (Date.parse(at) < start ||
        (link.ends_at !== null && Date.parse(at) > Date.parse(link.ends_at)))
    ) {
      notice.value = ''
      fieldErrors.value = {
        ends_at: [t('admin.priceTables.link.endOutOfRange', { period: labels.linkPeriod(link) })],
      }
      return Promise.resolve(false)
    }
    return changeDetail(async (table) => {
      await priceTableApi.endLink(link.court_id, { kind: table.kind, ends_at: at })
      return (await priceTableApi.show(table.id)).data
    }, t('admin.priceTables.endedNotice'))
  }

  async function retire() {
    const name = detail.value?.name
    if (!name || !window.confirm(t('admin.priceTables.retireConfirm', { name }))) return
    await changeDetail(
      async (table) => (await priceTableApi.retire(table.id)).data,
      t('admin.priceTables.retiredNotice'),
    )
  }

  const errorDialogProps = computed(() => {
    const error = errorDialog.currentError.value as PriceTableError | null
    if (error?.code === 'PRICE_COVERAGE_INCOMPLETE') {
      return {
        title: t('admin.priceTables.coverage.title'),
        message: t('admin.priceTables.coverage.message'),
        details: (error.missing_items ?? []).map(labels.missingItem),
        errorCode: error.code,
      }
    }
    if (error?.code === 'SUBSCRIPTION_EXPIRED' || error?.code === 'NO_ACTIVE_SUBSCRIPTION') {
      return { message: t('admin.priceTables.planRequired'), errorCode: error.code }
    }
    return { error }
  })

  return {
    venues,
    venueId,
    courts,
    tables,
    detail,
    loading,
    saving,
    notice,
    fieldErrors,
    errorOpen: errorDialog.isOpen,
    errorDialogProps,
    closeError: errorDialog.closeError,
    loadVenues,
    loadVenue,
    openDetail,
    create,
    createVersion,
    applyLinks,
    endLink,
    retire,
  }
}

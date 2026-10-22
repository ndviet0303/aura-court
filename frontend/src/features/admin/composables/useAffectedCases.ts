import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApiCall } from '@/core/composables/useApiCall'
import type { Court, Venue } from '@/features/court/types/court'
import { adminApi } from '../api/adminApi'
import { affectedApi } from '../api/affectedApi'
import type {
  AffectedCase,
  AffectedCaseFilters,
  CaseAction,
  CaseOutcomeForm,
  OperatorCommandForm,
} from '../types/affected'
import { failureMessage } from './useAffectedConfirm'
import { useOwnerCheck } from './useLifecycleCommands'

/**
 * Tasks 4.14 / 4.17: the owner's (every venue they own) or assigned staff's (their venues) handling cases, the
 * outcome form and the design D8 operator commands. The server scopes the list and refuses the rest with 403.
 */
export function useAffectedCases() {
  const { t } = useI18n()
  const isOwnerOf = useOwnerCheck()

  const filters = reactive<AffectedCaseFilters>({
    venue_id: '',
    court_id: '',
    source_type: '',
    status: '',
  })
  const venues = ref<Venue[]>([])
  const courts = ref<Court[]>([])
  const { data, loading, error, execute } = useApiCall(affectedApi.listCases, {
    initialLoading: true,
    defaultErrorMessage: t('admin.affected.loadError'),
  })
  const cases = computed(() => data.value?.data ?? [])
  const page = computed(() => data.value?.meta.current_page ?? 1)
  const lastPage = computed(() => data.value?.meta.last_page ?? 1)

  const action = ref<{ kind: CaseAction; row: AffectedCase } | null>(null)
  const saving = ref(false)
  const formError = ref('')
  const fieldErrors = ref<Record<string, string[]>>({})
  const notice = ref('')

  // Each value comes from that field's own dropdown options.
  const setFilter = (key: keyof AffectedCaseFilters, value: string | number) =>
    Object.assign(filters, { [key]: value })
  const filterCourts = computed(() =>
    filters.venue_id === ''
      ? courts.value
      : courts.value.filter((court) => court.venue_id === filters.venue_id),
  )
  const organizationOf = (row: AffectedCase) =>
    venues.value.find((venue) => venue.id === row.venue.id)?.organization_id ?? null
  // Only the owner sees and records the off-platform refund note (BR-38); the server answers 403 otherwise.
  const isOwnerOfCase = (row: AffectedCase) => isOwnerOf(organizationOf(row))

  /** Another court of the same sport in the booking's organization (design D8 move-court). */
  function moveTargets(row: AffectedCase): Court[] {
    const source = courts.value.find((court) => court.id === row.reservation.court_id)
    return courts.value.filter(
      (court) =>
        court.id !== row.reservation.court_id &&
        court.sport_type === source?.sport_type &&
        court.venue?.organization_id === organizationOf(row),
    )
  }

  // useApiCall keeps the message in `error`; the rethrow has nothing left to tell the view.
  const load = (target = 1) => execute({ ...filters }, target).catch(() => null)

  function open(kind: CaseAction, row: AffectedCase) {
    formError.value = ''
    fieldErrors.value = {}
    notice.value = ''
    action.value = { kind, row }
  }

  async function submit(form: CaseOutcomeForm | OperatorCommandForm) {
    const current = action.value
    if (!current || saving.value) return
    saving.value = true
    formError.value = ''
    fieldErrors.value = {}
    try {
      if (current.kind === 'outcome') {
        await affectedApi.recordOutcome(current.row.id, form as CaseOutcomeForm)
      } else {
        const { row, kind } = current
        await affectedApi.runCommand(
          organizationOf(row),
          row.reservation.code,
          kind,
          form as OperatorCommandForm,
        )
      }
      action.value = null
      notice.value = t(
        current.kind === 'outcome' ? 'admin.affected.outcomeSaved' : 'admin.affected.commandDone',
      )
      await load(page.value)
    } catch (cause: unknown) {
      fieldErrors.value = (cause as { errors?: Record<string, string[]> } | null)?.errors ?? {}
      formError.value = failureMessage(cause)
    } finally {
      saving.value = false
    }
  }

  watch(
    () => filters.venue_id,
    () => (filters.court_id = ''),
  )
  watch(filters, () => load())

  onMounted(async () => {
    const [venueList, courtList] = await Promise.allSettled([
      adminApi.getVenues(),
      adminApi.getCourts(),
    ])
    venues.value = venueList.status === 'fulfilled' ? (venueList.value ?? []) : []
    courts.value = courtList.status === 'fulfilled' ? (courtList.value ?? []) : []
    await load()
  })

  return {
    filters,
    setFilter,
    venues,
    filterCourts,
    cases,
    page,
    lastPage,
    loading,
    error,
    notice,
    action,
    saving,
    formError,
    fieldErrors,
    isOwnerOfCase,
    moveTargets,
    load,
    open,
    submit,
  }
}

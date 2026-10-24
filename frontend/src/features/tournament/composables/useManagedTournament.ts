import { computed, ref, watch, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { tournamentManageApi as manageApi } from '../api/tournamentManageApi'
import type {
  EntryPayload,
  Fixture,
  Grant,
  ManagedEntry,
  ManagedTournamentDetail,
  Permission,
  ResultPayload,
} from '../types/tournament'
import { apiProblem } from '../utils/tournament'

type Slot = { court_id: number; starts_at: string; ends_at: string }

/**
 * UC-7.17..UC-7.34: the management screen. `permissions` decides what is shown; every write reloads the
 * tournament, so entries, fixtures, standings and history always come from the server.
 */
export function useManagedTournament(id: Ref<string>) {
  const { t } = useI18n()
  const data = ref<ManagedTournamentDetail | null>(null)
  const loading = ref(false)
  const busy = ref(false)
  const error = ref('')

  async function load(quiet = false) {
    if (!quiet) loading.value = true
    try {
      data.value = await manageApi.show(id.value)
      if (!quiet) error.value = ''
    } catch (e) {
      error.value = errorMessage(e, t('tournament.loadError'))
    } finally {
      loading.value = false
    }
  }
  watch(id, () => load(), { immediate: true })

  /** Runs one write; the message stays in `error` for the page or the open dialog. */
  async function run(write: () => Promise<unknown>): Promise<boolean> {
    busy.value = true
    error.value = ''
    try {
      await write()
      await load(true)
      return true
    } catch (e) {
      error.value = apiProblem(e, t('tournament.manage.actionError'))
      return false
    } finally {
      busy.value = false
    }
  }

  const tournament = computed(() => data.value?.tournament ?? null)
  const can = (p: Permission) => !!tournament.value?.permissions[p]
  const tid = () => id.value

  function draw() {
    // BR-153: a redraw is allowed only before the start and before any result; the server decides.
    if (
      tournament.value?.status === 'drawn' &&
      !window.confirm(t('tournament.manage.redrawConfirm'))
    )
      return Promise.resolve(false)
    return run(() => manageApi.draw(tid()))
  }

  return {
    data,
    tournament,
    loading,
    busy,
    error,
    can,
    load,
    clearError: () => (error.value = ''),
    publish: () => run(() => manageApi.publish(tid())),
    lockRoster: () => run(() => manageApi.lockRoster(tid())),
    draw,
    stop: (reason: string) => run(() => manageApi.stop(tid(), reason.trim())),
    allocate: (slot: Slot) => run(() => manageApi.allocate(tid(), slot)),
    release: (allocationId: number) => run(() => manageApi.release(tid(), allocationId)),
    grant: (role: Grant['role'], email: string) =>
      run(() => manageApi.grant(tid(), { role, email: email.trim() })),
    revoke: (grant: Grant) => run(() => manageApi.revoke(tid(), grant.id)),
    approve: (entry: ManagedEntry) => run(() => manageApi.approve(entry.id)),
    reject: (entry: ManagedEntry, reason: string) =>
      run(() => manageApi.reject(entry.id, reason.trim())),
    addEntry: (payload: EntryPayload) => run(() => manageApi.addRosterEntry(tid(), payload)),
    removeEntry: (entry: ManagedEntry) => run(() => manageApi.removeEntry(entry.id)),
    schedule: (fixture: Fixture, slot: Slot) => run(() => manageApi.schedule(fixture.id, slot)),
    result: (fixture: Fixture, payload: ResultPayload) =>
      run(() => manageApi.result(fixture.id, payload)),
    correct: (fixture: Fixture, payload: ResultPayload, reason: string) =>
      run(() => manageApi.correct(fixture.id, { ...payload, reason: reason.trim() })),
  }
}

export type ManagedTournament = ReturnType<typeof useManagedTournament>

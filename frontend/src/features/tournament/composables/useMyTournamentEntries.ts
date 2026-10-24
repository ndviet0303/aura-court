import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { tournamentApi } from '../api/tournamentApi'
import type { MyEntry } from '../types/tournament'

const POLL_MS = 10_000

/**
 * UC-7.22, UC-7.25: the customer's entries. Payment opens only once approved; while one is pending the list
 * is polled every 10 s, because the SePay webhook confirms it on the server (D23).
 */
export function useMyTournamentEntries() {
  const { t } = useI18n()
  const entries = ref<MyEntry[]>([])
  const loading = ref(false)
  const acting = ref<number | null>(null)
  const error = ref('')
  let timer: ReturnType<typeof setInterval> | undefined

  /** quiet: a background refresh keeps the list and skips the spinner. */
  async function load(quiet = false) {
    if (!quiet) loading.value = true
    try {
      entries.value = (await tournamentApi.myEntries()).data
      if (!quiet) error.value = ''
    } catch (e) {
      if (!quiet) error.value = errorMessage(e, t('tournament.me.loadError'))
    } finally {
      loading.value = false
    }
  }

  async function act(entry: MyEntry, run: () => Promise<void>) {
    acting.value = entry.id
    error.value = ''
    try {
      await run()
    } catch (e) {
      error.value = errorMessage(e, t('tournament.me.actionError'))
    } finally {
      acting.value = null
    }
  }

  const replace = (id: number, patch: Partial<MyEntry>) =>
    (entries.value = entries.value.map((e) => (e.id === id ? { ...e, ...patch } : e)))

  const withdraw = (entry: MyEntry) =>
    act(entry, async () => {
      if (!window.confirm(t('tournament.me.withdrawConfirm', { name: entry.tournament.name })))
        return
      replace(entry.id, (await tournamentApi.withdraw(entry.id)).entry)
    })

  const pay = (entry: MyEntry) =>
    act(entry, async () => {
      const res = await tournamentApi.pay(entry.id)
      replace(entry.id, { ...res.entry, payment: res.payment })
    })

  const paying = computed(() =>
    entries.value.some((e) => e.status === 'approved' && e.payment?.status === 'pending'),
  )
  watch(paying, (on) => {
    clearInterval(timer)
    if (on) timer = setInterval(() => void load(true), POLL_MS)
  })

  onMounted(() => load())
  onBeforeUnmount(() => clearInterval(timer))

  return { entries, loading, acting, error, withdraw, pay, refresh: () => load(true) }
}

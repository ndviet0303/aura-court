import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { tournamentApi } from '../api/tournamentApi'
import type { Sport, TournamentStatus, TournamentSummary } from '../types/tournament'

/** FR-122: published public tournaments, filtered by sport and status, page by page. */
export function useTournamentList() {
  const { t } = useI18n()
  const filters = reactive<{ sport: Sport | ''; status: TournamentStatus | '' }>({
    sport: '',
    status: '',
  })
  const items = ref<TournamentSummary[]>([])
  const page = ref(1)
  const lastPage = ref(1)
  const loading = ref(false)
  const error = ref('')
  let latest = 0

  async function load(target = 1) {
    const request = ++latest
    loading.value = true
    error.value = ''
    try {
      const res = await tournamentApi.list({ ...filters }, target)
      if (request !== latest) return
      items.value = target === 1 ? res.data : [...items.value, ...res.data]
      page.value = res.meta.current_page
      lastPage.value = res.meta.last_page
    } catch (e) {
      if (request === latest) error.value = errorMessage(e, t('tournament.loadError'))
    } finally {
      if (request === latest) loading.value = false
    }
  }
  watch(filters, () => load(1), { immediate: true })

  return {
    filters,
    items,
    loading,
    error,
    hasMore: computed(() => page.value < lastPage.value),
    loadMore: () => load(page.value + 1),
  }
}

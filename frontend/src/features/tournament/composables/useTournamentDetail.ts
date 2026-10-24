import { ref, watch, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { tournamentApi } from '../api/tournamentApi'
import type { TournamentDetail } from '../types/tournament'

/** FR-122: one tournament with its accepted entries, fixtures and standings. */
export function useTournamentDetail(id: Ref<string>) {
  const { t } = useI18n()
  const detail = ref<TournamentDetail | null>(null)
  const loading = ref(false)
  const error = ref('')

  async function load() {
    loading.value = true
    error.value = ''
    try {
      detail.value = await tournamentApi.show(id.value)
    } catch (e) {
      detail.value = null
      error.value = errorMessage(e, t('tournament.loadError'))
    } finally {
      loading.value = false
    }
  }
  watch(id, load, { immediate: true })

  return { detail, loading, error, load }
}

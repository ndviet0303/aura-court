import { computed, ref, watch, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { tournamentApi } from '../api/tournamentApi'
import { apiProblem } from '../utils/tournament'
import { useEntryForm } from './useEntryForm'
import { useTournamentDetail } from './useTournamentDetail'

/** FR-123, FR-124: a customer registers one unit; the owner reviews it before any fee is paid. */
export function useTournamentRegistration(id: Ref<string>) {
  const { t } = useI18n()
  const router = useRouter()
  const { detail, loading, error: loadError } = useTournamentDetail(id)
  const tournament = computed(() => detail.value?.tournament ?? null)
  const form = useEntryForm(tournament)
  const submitting = ref(false)
  const error = ref('')
  const showProblem = ref(false)

  watch(tournament, form.reset)

  async function submit() {
    showProblem.value = true
    if (form.problem.value || !tournament.value) return
    submitting.value = true
    error.value = ''
    try {
      await tournamentApi.register(id.value, form.payload())
      await router.push({ name: 'tournament-my-entries' })
    } catch (e) {
      error.value = apiProblem(e, t('tournament.register.error'))
    } finally {
      submitting.value = false
    }
  }

  return { tournament, loading, loadError, form, submitting, error, showProblem, submit }
}

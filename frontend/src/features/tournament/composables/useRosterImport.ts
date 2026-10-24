import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { tournamentManageApi } from '../api/tournamentManageApi'
import type { RosterPreview, RosterRowError } from '../types/tournament'
import { apiProblem } from '../utils/tournament'

/**
 * UC-7.27, D24: the internal roster from the .xlsx template. Preview writes nothing; confirm replaces the
 * whole internal roster and fails with the same row errors when the file is not clean.
 */
export function useRosterImport(id: () => string, onConfirmed: () => void) {
  const { t } = useI18n()
  const file = ref<File | null>(null)
  const preview = ref<RosterPreview | null>(null)
  const working = ref(false)
  const error = ref('')

  async function step(work: () => Promise<void>) {
    working.value = true
    error.value = ''
    try {
      await work()
    } catch (e) {
      const rowErrors = (e as { errors?: unknown }).errors
      if (Array.isArray(rowErrors) && preview.value) {
        preview.value = { ...preview.value, errors: rowErrors as RosterRowError[] }
      }
      error.value = apiProblem(e, t('tournament.roster.importError'))
    } finally {
      working.value = false
    }
  }

  const choose = (picked: File | null) =>
    step(async () => {
      file.value = picked
      preview.value = picked ? await tournamentManageApi.previewRoster(id(), picked) : null
    })

  const confirm = () =>
    step(async () => {
      if (!file.value) return
      await tournamentManageApi.confirmRoster(id(), file.value)
      file.value = null
      preview.value = null
      onConfirmed()
    })

  const template = () => step(() => tournamentManageApi.downloadTemplate(id()))

  return { file, preview, working, error, choose, confirm, template }
}

import { ref, watch, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { suspensionApi } from '../api/suspensionApi'
import type { Remediation, RemediationDecision } from '../types/suspension'
import { failureMessage } from './useAffectedConfirm'

/** Task 5.10: one submission with its decision and full history, and the admin's review (D14). */
export function useRemediationReview(remediationId: Ref<number>) {
  const { t } = useI18n()
  const loading = ref(false)
  const error = ref('')
  const remediation = ref<Remediation | null>(null)
  const saving = ref(false)
  const reviewError = ref('')
  const fieldErrors = ref<Record<string, string[]>>({})
  const notice = ref('')

  async function load() {
    loading.value = true
    error.value = ''
    try {
      remediation.value = (await suspensionApi.getRemediation(remediationId.value)).data
    } catch (cause: unknown) {
      error.value = errorMessage(cause, t('admin.remediation.loadError'))
    } finally {
      loading.value = false
    }
  }

  async function review(payload: { decision: RemediationDecision; feedback: string }) {
    if (saving.value) return
    saving.value = true
    reviewError.value = ''
    fieldErrors.value = {}
    try {
      const response = await suspensionApi.reviewRemediation(remediationId.value, payload)
      remediation.value = response.data
      notice.value = response.message
    } catch (cause: unknown) {
      fieldErrors.value = (cause as { errors?: Record<string, string[]> } | null)?.errors ?? {}
      reviewError.value = failureMessage(cause)
      // 409: already reviewed or superseded; reload so the admin sees the current state.
      if ((cause as { status?: number } | null)?.status === 409) await load()
    } finally {
      saving.value = false
    }
  }

  watch(remediationId, load, { immediate: true })

  return { loading, error, remediation, saving, reviewError, fieldErrors, notice, review }
}

import { computed, ref, watch, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { suspensionApi } from '../api/suspensionApi'
import type { Suspension } from '../types/suspension'
import { failureMessage } from './useAffectedConfirm'
import { useOwnerCheck } from './useLifecycleCommands'

// A first submission, or a resubmission after more info was requested or a rejection (D14).
const OPEN_FOR_SUBMISSION = [null, 'more_info_requested', 'rejected']

/**
 * Task 5.11: the owner's suspension decisions of a venue (newest first), the open decision with its
 * remediation history (oldest first) and the remediation submission (D14).
 */
export function useVenueSuspensions(
  organizationId: Ref<number>,
  venueId: Ref<number>,
  suspensionId: Ref<number>,
) {
  const { t } = useI18n()

  const loading = ref(false)
  const error = ref('')
  const suspensions = ref<Suspension[]>([])
  const decision = ref<Suspension | null>(null)

  const submitting = ref(false)
  const submitError = ref('')
  const fieldErrors = ref<Record<string, string[]>>({})
  const notice = ref('')

  const canSubmit = computed(
    () =>
      !!decision.value?.is_active &&
      OPEN_FOR_SUBMISSION.includes(decision.value.remediation_status),
  )

  async function load() {
    loading.value = true
    error.value = ''
    try {
      suspensions.value = (
        await suspensionApi.listVenueSuspensions(organizationId.value, venueId.value)
      ).data
      decision.value = (
        await suspensionApi.getVenueSuspension(
          organizationId.value,
          venueId.value,
          suspensionId.value,
        )
      ).data
    } catch (cause: unknown) {
      error.value = errorMessage(cause, t('admin.suspension.loadError'))
    } finally {
      loading.value = false
    }
  }

  async function submit(content: string, files: File[]) {
    const current = decision.value
    if (!current || submitting.value) return
    const form = new FormData()
    form.append('content', content)
    files.forEach((file) => form.append('files[]', file))
    submitting.value = true
    submitError.value = ''
    fieldErrors.value = {}
    try {
      const response = await suspensionApi.submitRemediation(
        organizationId.value,
        venueId.value,
        current.id,
        form,
      )
      notice.value = response.message
      await load()
    } catch (cause: unknown) {
      // 422 lists `content`, `files` and `files.N`; 409 says why a submission is not possible now.
      fieldErrors.value = (cause as { errors?: Record<string, string[]> } | null)?.errors ?? {}
      submitError.value = failureMessage(cause)
    } finally {
      submitting.value = false
    }
  }

  watch([organizationId, venueId, suspensionId], load, { immediate: true })

  return {
    loading,
    error,
    suspensions,
    decision,
    canSubmit,
    submitting,
    submitError,
    fieldErrors,
    notice,
    submit,
  }
}

/**
 * Task 5.11: the venue's active suspension for the owner's banner. The partner venue payload does not carry it and
 * the suspension reads are owner-only, so staff get null without a request.
 */
export function useActiveSuspension(organizationId: number, venueId: number) {
  const isOwnerOf = useOwnerCheck()
  const active = ref<Suspension | null>(null)

  async function load() {
    if (!isOwnerOf(organizationId)) return
    // The banner is advisory; the venue page still works when this read fails.
    const list = await suspensionApi
      .listVenueSuspensions(organizationId, venueId)
      .then((response) => response.data)
      .catch(() => [])
    active.value = list.find((item) => item.is_active) ?? null
  }

  return { active, load }
}

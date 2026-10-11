import { ref, computed } from 'vue'
import { partnerRegistrationApi } from '../api/partnerRegistrationApi'
import type {
  PartnerApplication,
  PartnerDocument,
  PartnerReviewHistory,
  SaveDraftPayload,
  SubmitApplicationPayload,
} from '../types'
import { errorMessage } from '@/core/utils/errorMessage'

export function usePartnerRegistration() {
  const application = ref<PartnerApplication | null>(null)
  const history = ref<PartnerReviewHistory[]>([])
  const loading = ref(false)
  const saving = ref(false)
  const submitting = ref(false)
  const uploading = ref(false)
  const error = ref<string | null>(null)
  const fieldErrors = ref<Record<string, string[]>>({})

  const isDraft = computed(() => application.value?.status === 'draft')
  const isPendingReview = computed(() =>
    ['pending', 'pending_review'].includes(application.value?.status ?? ''),
  )
  const isSupplementationRequested = computed(() =>
    ['revision_required', 'supplementation_requested'].includes(application.value?.status ?? ''),
  )
  const isApproved = computed(() => application.value?.status === 'approved')
  const isRejected = computed(() => application.value?.status === 'rejected')

  function clearErrors() {
    error.value = null
    fieldErrors.value = {}
  }

  function extractErrors(err: unknown) {
    error.value = errorMessage(err)
    if (
      err &&
      typeof err === 'object' &&
      'errors' in err &&
      typeof err.errors === 'object' &&
      err.errors !== null
    ) {
      fieldErrors.value = err.errors as Record<string, string[]>
    }
  }
  async function fetchCurrent(): Promise<PartnerApplication | null> {
    loading.value = true
    clearErrors()
    try {
      const res = await partnerRegistrationApi.getCurrentApplication()
      application.value = res.data
      return res.data
    } catch (err) {
      extractErrors(err)
      return null
    } finally {
      loading.value = false
    }
  }

  async function start(): Promise<PartnerApplication | null> {
    submitting.value = true
    clearErrors()
    try {
      const res = await partnerRegistrationApi.startApplication()
      application.value = res.data
      return res.data
    } catch (err) {
      extractErrors(err)
      throw err
    } finally {
      submitting.value = false
    }
  }

  async function saveDraft(payload: SaveDraftPayload): Promise<PartnerApplication | null> {
    if (!application.value) return null
    saving.value = true
    clearErrors()
    try {
      const res = await partnerRegistrationApi.saveDraft(application.value.id, payload)
      application.value = res.data
      return res.data
    } catch (err) {
      extractErrors(err)
      throw err
    } finally {
      saving.value = false
    }
  }

  async function uploadDocument(file: File, documentType: string): Promise<PartnerDocument | null> {
    if (!application.value) return null
    uploading.value = true
    clearErrors()
    try {
      const formData = new FormData()
      formData.append('file', file)
      formData.append('document_type', documentType)

      const res = await partnerRegistrationApi.uploadDocument(application.value.id, formData)
      // Refresh or append document to current application
      if (application.value) {
        const docs = application.value.documents.filter((d) => d.id !== res.data.id)
        docs.push(res.data)
        application.value.documents = docs
        if (res.data.application_version) application.value.version = res.data.application_version
      }
      return res.data
    } catch (err) {
      extractErrors(err)
      throw err
    } finally {
      uploading.value = false
    }
  }

  async function submit(payload: SubmitApplicationPayload): Promise<PartnerApplication | null> {
    if (!application.value) return null
    submitting.value = true
    clearErrors()
    try {
      const res = await partnerRegistrationApi.submitApplication(application.value.id, payload)
      application.value = res.data
      return res.data
    } catch (err) {
      extractErrors(err)
      throw err
    } finally {
      submitting.value = false
    }
  }

  async function fetchHistory(): Promise<PartnerReviewHistory[]> {
    if (!application.value) return []
    try {
      const res = await partnerRegistrationApi.getApplicationHistory(application.value.id)
      history.value = res.data
      return res.data
    } catch (err) {
      extractErrors(err)
      return []
    }
  }

  return {
    application,
    history,
    loading,
    saving,
    submitting,
    uploading,
    error,
    fieldErrors,
    isDraft,
    isPendingReview,
    isSupplementationRequested,
    isApproved,
    isRejected,
    clearErrors,
    fetchCurrent,
    start,
    saveDraft,
    uploadDocument,
    submit,
    fetchHistory,
  }
}

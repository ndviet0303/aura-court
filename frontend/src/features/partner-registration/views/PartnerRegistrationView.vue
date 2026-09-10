<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { usePartnerRegistration } from '../composables/usePartnerRegistration'
import BusinessInfoStep from '../components/BusinessInfoStep.vue'
import DocumentUploadStep from '../components/DocumentUploadStep.vue'
import ConsentAndReviewStep from '../components/ConsentAndReviewStep.vue'
import type { SaveDraftPayload, SubmitApplicationPayload } from '../types'

const router = useRouter()
const {
  application,
  loading,
  saving,
  submitting,
  uploading,
  error,
  fieldErrors,
  fetchCurrent,
  start,
  saveDraft,
  uploadDocument,
  submit,
} = usePartnerRegistration()

const currentStep = ref(1)
const termsAccepted = ref(false)
const privacyAccepted = ref(false)
const consentError = ref('')

onMounted(async () => {
  const current = await fetchCurrent()
  if (current) {
    if (current.status !== 'draft') {
      router.push('/partner/application-status')
    }
  }
})

async function handleStartApplication() {
  consentError.value = ''
  if (!termsAccepted.value || !privacyAccepted.value) {
    consentError.value = 'Vui lòng đọc và đồng ý với điều khoản dịch vụ đối tác và chính sách dữ liệu.'
    return
  }
  try {
    await start({ consent_terms: termsAccepted.value, consent_privacy: privacyAccepted.value, terms_version: 'v1.0' })
    currentStep.value = 1
  } catch {}
}
async function handleSaveDraft(payload: SaveDraftPayload) {
  try { await saveDraft(payload) } catch {}
}
async function handleUpload(payload: { file: File; documentType: string }) {
  try { await uploadDocument(payload.file, payload.documentType) } catch {}
}
async function handleSubmit(payload: SubmitApplicationPayload) {
  try {
    await submit(payload)
    router.push('/partner/application-status')
  } catch {}
}
</script>

<template>
  <div class="partner-registration-view py-8 px-4 max-w-4xl mx-auto">
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
        Đăng ký Trở thành Đối tác Cho thuê Sân
      </h1>
      <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">
        Quy trình xét duyệt minh bạch, an toàn dành cho các chủ câu lạc bộ và cơ sở thể thao.
      </p>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12" data-testid="loading-spinner">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-600"></div>
    </div>

    <!-- Error Alert -->
    <div
      v-else-if="error && !application"
      class="p-4 mb-6 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-900/30 dark:text-red-400"
      role="alert"
    >
      {{ error }}
    </div>

    <!-- Initial Consent Step (if no draft application yet) -->
    <div v-else-if="!application" class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-100 dark:border-gray-700">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
        1. Cam kết & Điều khoản Đối tác
      </h2>
      <div class="prose dark:prose-invert text-sm text-gray-600 dark:text-gray-300 mb-6 space-y-2 bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg">
        <p>
          Bằng việc đăng ký tài khoản đối tác, bạn cam kết cung cấp thông tin trung thực về pháp nhân/hộ kinh doanh và cơ sở sân bãi.
        </p>
        <p>
          Hồ sơ của bạn sẽ được đội ngũ vận hành Aura Sports kiểm duyệt độc lập trước khi mở quyền quản lý kinh doanh và xuất bản sân bãi.
        </p>
      </div>

      <div class="space-y-3 mb-6">
        <label class="flex items-start gap-3 cursor-pointer">
          <input
            v-model="termsAccepted"
            type="checkbox"
            class="mt-1 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
          />
          <span class="text-sm text-gray-700 dark:text-gray-200">
            Tôi đã đọc, hiểu và đồng ý với <strong>Quy chế Hoạt động Đối tác (phiên bản v1.0)</strong>.
          </span>
        </label>

        <label class="flex items-start gap-3 cursor-pointer">
          <input
            v-model="privacyAccepted"
            type="checkbox"
            class="mt-1 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
          />
          <span class="text-sm text-gray-700 dark:text-gray-200">
            Tôi đồng ý với chính sách xử lý dữ liệu và xác minh định danh kinh doanh.
          </span>
        </label>
      </div>

      <div v-if="consentError" class="text-xs text-red-600 dark:text-red-400 mb-4">
        {{ consentError }}
      </div>

      <button
        type="button"
        class="inline-flex items-center justify-center px-6 py-2.5 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700"
        @click="handleStartApplication"
      >
        Bắt đầu Hồ sơ Đăng ký
      </button>
    </div>

    <!-- Application in Draft: Step-by-step Wizard -->
    <div v-else class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-100 dark:border-gray-700 space-y-6">
      <!-- Steps Progress Bar -->
      <div class="flex items-center justify-between border-b pb-4 dark:border-gray-700">
        <div class="flex items-center gap-2 sm:gap-4">
          <button type="button" class="text-xs font-semibold px-3 py-1 rounded-full" :class="currentStep === 1 ? 'bg-emerald-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'" @click="currentStep = 1">1. Thông tin kinh doanh</button>
          <button type="button" class="text-xs font-semibold px-3 py-1 rounded-full" :class="currentStep === 2 ? 'bg-emerald-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'" @click="currentStep = 2">2. Tài liệu xác thực</button>
          <button type="button" class="text-xs font-semibold px-3 py-1 rounded-full" :class="currentStep === 3 ? 'bg-emerald-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'" @click="currentStep = 3">3. Rà soát & Nộp hồ sơ</button>
        </div>
        <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Dự thảo #{{ application.id }}</span>
      </div>

      <!-- Step 1: Business Information -->
      <BusinessInfoStep
        v-if="currentStep === 1"
        :application="application"
        :field-errors="fieldErrors"
        :saving="saving"
        @save="handleSaveDraft"
        @next="currentStep = 2"
      />

      <!-- Step 2: Document Upload -->
      <DocumentUploadStep
        v-else-if="currentStep === 2"
        :application="application"
        :uploading="uploading"
        :field-errors="fieldErrors"
        @upload="handleUpload"
        @back="currentStep = 1"
        @next="currentStep = 3"
      />

      <!-- Step 3: Consent and Review -->
      <ConsentAndReviewStep
        v-else-if="currentStep === 3"
        :application="application"
        :submitting="submitting"
        :error="error"
        :field-errors="fieldErrors"
        @submit="handleSubmit"
        @back="currentStep = 2"
      />
    </div>
  </div>
</template>

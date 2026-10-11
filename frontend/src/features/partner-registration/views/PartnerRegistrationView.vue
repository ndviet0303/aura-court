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

function goToStep(step: number) {
  currentStep.value = step
  if (typeof window !== 'undefined') {
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

onMounted(async () => {
  if (typeof window !== 'undefined') {
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
  const current = await fetchCurrent()
  if (current) {
    if (!['draft', 'revision_required', 'supplementation_requested'].includes(current.status)) {
      router.push('/partner/application-status')
    }
  }
})
async function handleStartApplication() {
  try {
    await start()
    currentStep.value = 1
  } catch {}
}
async function handleSaveDraft(payload: SaveDraftPayload): Promise<boolean> {
  try {
    return (await saveDraft(payload)) !== null
  } catch {
    return false
  }
}
async function handleUpload(payload: { file: File; documentType: string }) {
  try {
    await uploadDocument(payload.file, payload.documentType)
  } catch {}
}
async function handleSubmit(payload: SubmitApplicationPayload) {
  try {
    await submit(payload)
    router.push('/partner/application-status')
  } catch {}
}
</script>

<template>
  <div class="partner-page-wrapper">
    <div class="aura-glow-bg" aria-hidden="true"></div>

    <div class="partner-container">
      <!-- Loading State -->
      <div v-if="loading" class="partner-card loading-card" data-testid="loading-spinner">
        <div class="spinner-ring"></div>
        <p class="loading-text">Đang tải hồ sơ đối tác...</p>
      </div>

      <!-- Error Alert -->
      <div v-else-if="error && !application" class="error-banner" role="alert">
        <span class="material-symbols-outlined error-icon">error</span>
        <span class="error-text">{{ error }}</span>
      </div>

      <!-- Initial Consent Step (if no draft application yet) -->
      <div v-else-if="!application" class="partner-card welcome-card">
        <div class="partner-header text-center">
          <div class="partner-chip">
            <span class="material-symbols-outlined icon-16">handshake</span>
            <span>AURA PARTNER NETWORK</span>
          </div>
          <h1 class="partner-title">Đăng ký Trở thành Đối tác Cho thuê Sân</h1>
          <p class="partner-subtitle">
            Quy trình xét duyệt minh bạch, an toàn dành cho các chủ câu lạc bộ và cơ sở thể thao
            trên toàn quốc.
          </p>
        </div>

        <div class="partner-benefits-grid">
          <div class="benefit-card">
            <div class="benefit-icon-box">
              <span class="material-symbols-outlined">bolt</span>
            </div>
            <h4>Vận hành Tự động</h4>
            <p>
              Bảng giá linh hoạt theo giờ, theo ngày và hệ thống lịch đặt realtime chống trùng sân.
            </p>
          </div>

          <div class="benefit-card">
            <div class="benefit-icon-box">
              <span class="material-symbols-outlined">payments</span>
            </div>
            <h4>Thanh toán Minh bạch</h4>
            <p>
              Hỗ trợ QR Code thanh toán trực tiếp về tài khoản chủ sân, đối soát minh bạch 24/7.
            </p>
          </div>

          <div class="benefit-card">
            <div class="benefit-icon-box">
              <span class="material-symbols-outlined">groups</span>
            </div>
            <h4>Hàng ngàn Khách hàng</h4>
            <p>
              Kết nối ngay với cộng đồng người chơi thể thao năng động trên nền tảng Aura Sports.
            </p>
          </div>
        </div>

        <div class="consent-info-box">
          <div class="info-head">
            <span class="material-symbols-outlined info-icon">verified_user</span>
            <div class="info-text-wrap">
              <h4>Bắt đầu hồ sơ đối tác</h4>
              <p>
                Hồ sơ của bạn sẽ được đội ngũ vận hành Aura Sports kiểm duyệt trước khi mở quyền
                quản lý và xuất bản cơ sở. Bạn sẽ xem và xác nhận các điều khoản cần thiết trước khi
                nộp hồ sơ.
              </p>
            </div>
          </div>
        </div>

        <div class="action-footer">
          <button type="button" class="btn-start-action" @click="handleStartApplication">
            <span>Bắt đầu Hồ sơ Đăng ký</span>
            <span class="material-symbols-outlined icon-18">arrow_forward</span>
          </button>
        </div>
      </div>

      <!-- Application in Draft: Step-by-step Wizard -->
      <div v-else class="partner-card wizard-card">
        <div class="wizard-header">
          <div>
            <div class="partner-chip">
              <span class="material-symbols-outlined icon-16">edit_document</span>
              <span>HỒ SƠ ĐỐI TÁC</span>
            </div>
            <h1 class="wizard-title">Đăng ký Trở thành Đối tác Cho thuê Sân</h1>
          </div>
          <span class="app-code-badge">Hồ sơ #{{ application.id }}</span>
        </div>

        <!-- Steps Progress Bar -->
        <div class="wizard-stepper">
          <button
            type="button"
            class="step-pill"
            :class="{ active: currentStep === 1, completed: currentStep > 1 }"
            @click="goToStep(1)"
          >
            <span class="step-num">1</span>
            <span class="step-lbl">1. Thông tin kinh doanh</span>
          </button>

          <span class="step-sep material-symbols-outlined">chevron_right</span>

          <button
            type="button"
            class="step-pill"
            :class="{ active: currentStep === 2, completed: currentStep > 2 }"
            @click="goToStep(2)"
          >
            <span class="step-num">2</span>
            <span class="step-lbl">2. Tài liệu xác thực</span>
          </button>

          <span class="step-sep material-symbols-outlined">chevron_right</span>

          <button
            type="button"
            class="step-pill"
            :class="{ active: currentStep === 3, completed: currentStep > 3 }"
            @click="goToStep(3)"
          >
            <span class="step-num">3</span>
            <span class="step-lbl">3. Rà soát & Nộp hồ sơ</span>
          </button>
        </div>

        <!-- Step 1: Business Information -->
        <BusinessInfoStep
          v-if="currentStep === 1"
          :application="application"
          :field-errors="fieldErrors"
          :error="error"
          :saving="saving"
          :save="handleSaveDraft"
          @next="goToStep(2)"
        />

        <!-- Step 2: Document Upload -->
        <DocumentUploadStep
          v-else-if="currentStep === 2"
          :application="application"
          :uploading="uploading"
          :field-errors="fieldErrors"
          @upload="handleUpload"
          @back="goToStep(1)"
          @next="goToStep(3)"
        />

        <!-- Step 3: Consent and Review -->
        <ConsentAndReviewStep
          v-else-if="currentStep === 3"
          :application="application"
          :submitting="submitting"
          :error="error"
          :field-errors="fieldErrors"
          @submit="handleSubmit"
          @back="goToStep(2)"
        />
      </div>
    </div>
  </div>
</template>

<style src="../styles/partner-registration.css"></style>

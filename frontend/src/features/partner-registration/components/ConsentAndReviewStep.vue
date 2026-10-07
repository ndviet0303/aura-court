<script setup lang="ts">
import { ref } from 'vue'
import type { PartnerApplication, PartnerConsentType, SubmitApplicationPayload } from '../types'

const props = defineProps<{
  application: PartnerApplication
  submitting: boolean
  error?: string | null
  fieldErrors?: Record<string, string[]>
}>()

const emit = defineEmits<{
  (e: 'submit', payload: SubmitApplicationPayload): void
  (e: 'back'): void
}>()

const acceptedConsents = ref<PartnerConsentType[]>([])
const localError = ref('')
const consentOptions: Array<{ type: PartnerConsentType; label: string }> = [
  { type: 'cooperation_terms', label: 'Tôi đồng ý với quy chế hợp tác đối tác.' },
  { type: 'platform_fee_policy', label: 'Tôi đã đọc và chấp nhận chính sách phí dịch vụ nền tảng.' },
  { type: 'settlement_refund_terms', label: 'Tôi chấp nhận trách nhiệm thanh toán trực tiếp và xử lý hoàn tiền theo chính sách đã công bố.' },
  { type: 'data_processing_agreement', label: 'Tôi đồng ý với thỏa thuận xử lý dữ liệu và xác minh thông tin.' },
  { type: 'service_responsibility', label: 'Tôi hiểu trách nhiệm của mình khi sử dụng dịch vụ và cung cấp thông tin hồ sơ.' },
]

function handleSubmit() {
  localError.value = ''
  if (acceptedConsents.value.length !== consentOptions.length) {
    localError.value = 'Vui lòng xác nhận toàn bộ cam kết và chính sách trước khi nộp hồ sơ.'
    return
  }

  const payload: SubmitApplicationPayload = {
    expected_version: props.application.version,
    consents: consentOptions.map(({ type }) => ({ consent_type: type, terms_version: 'v1.0' })),
  }
  emit('submit', payload)
}

function maskTaxCode(code?: string | null): string {
  if (!code) return 'Không có'
  if (code.length <= 4) return '***'
  return code.slice(0, 3) + '***' + code.slice(-3)
}
</script>

<template>
  <div class="consent-and-review-step">
    <div class="step-head">
      <h3 class="step-title">
        3. Rà soát Hồ sơ & Xác nhận Cam kết
      </h3>
      <p class="step-sub">
        Kiểm tra lại toàn bộ thông tin đã nhập trước khi chính thức nộp vào hàng đợi xét duyệt.
      </p>
    </div>

    <!-- Review Summary Card -->
    <div class="review-summary-card">
      <div class="summary-info-grid">
        <div class="summary-item">
          <span class="summary-lbl">Mô hình kinh doanh</span>
          <span class="summary-val">
            {{ application.business_type === 'company' ? 'Doanh nghiệp' : application.business_type === 'household' ? 'Hộ kinh doanh' : 'Cá nhân' }}
          </span>
        </div>
        <div class="summary-item">
          <span class="summary-lbl">Tên cơ sở / Doanh nghiệp</span>
          <span class="summary-val">
            {{ application.business_name || 'Chưa cung cấp' }}
          </span>
        </div>
        <div class="summary-item">
          <span class="summary-lbl">Mã số thuế</span>
          <span class="summary-val font-mono">
            {{ maskTaxCode(application.tax_code) }}
          </span>
        </div>
        <div class="summary-item">
          <span class="summary-lbl">Người đại diện liên hệ</span>
          <span class="summary-val">
            {{ application.contact_name || 'Chưa cung cấp' }} ({{ application.contact_phone || 'N/A' }})
          </span>
        </div>
      </div>

      <div class="summary-docs-box">
        <span class="docs-section-lbl">Tài liệu đã tải lên ({{ application.documents?.length || 0 }} tệp)</span>
        <ul class="uploaded-docs-list">
          <li v-for="doc in application.documents" :key="doc.id" class="uploaded-doc-row">
            <span class="material-symbols-outlined icon-16 text-green">check_circle</span>
            <span class="doc-file-name">{{ doc.file_name }}</span>
            <span class="doc-file-type">({{ doc.document_type }})</span>
          </li>
        </ul>
      </div>
    </div>

    <!-- Versioned Consents -->
    <div class="consents-section">
      <label v-for="consent in consentOptions" :key="consent.type" class="consent-card" :class="{ 'is-checked': acceptedConsents.includes(consent.type) }">
        <input
          v-model="acceptedConsents"
          type="checkbox"
          :value="consent.type"
          class="consent-checkbox"
        />
        <span class="consent-label-text">
          {{ consent.label }}
        </span>
      </label>
    </div>

    <div v-if="localError || error" class="error-banner" role="alert">
      <span class="material-symbols-outlined icon-16">error</span>
      <span>{{ localError || error }}</span>
    </div>

    <!-- Navigation Buttons -->
    <div class="step-footer">
      <button
        type="button"
        class="btn-back-step"
        @click="emit('back')"
      >
        <span class="material-symbols-outlined icon-16">arrow_back</span>
        <span>Quay lại</span>
      </button>

      <button
        type="button"
        :disabled="submitting"
        class="btn-submit-step"
        @click="handleSubmit"
      >
        <span v-if="submitting" class="material-symbols-outlined icon-16 spin">sync</span>
        <span>{{ submitting ? 'Đang gửi duyệt...' : 'Nộp Hồ sơ Xét duyệt' }}</span>
        <span v-if="!submitting" class="material-symbols-outlined icon-18">send</span>
      </button>
    </div>
  </div>
</template>

<style src="../styles/partner-registration.css"></style>

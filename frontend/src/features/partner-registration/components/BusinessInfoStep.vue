<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import type { BusinessType, PartnerApplication, SaveDraftPayload } from '../types'

type TextFieldKey =
  | 'business_name'
  | 'legal_name'
  | 'tax_code'
  | 'representative_name'
  | 'id_card_number'
  | 'bank_name'
  | 'bank_code'
  | 'bank_account_number'
  | 'bank_account_holder'
  | 'contact_name'
  | 'contact_phone'
  | 'contact_email'
  | 'address_line'
  | 'ward'
  | 'district'
  | 'city'
  | 'postal_code'
  | 'lighting_spec'

const props = defineProps<{
  application: PartnerApplication
  fieldErrors: Record<string, string[]>
  error?: string | null
  saving: boolean
  save: (payload: SaveDraftPayload) => Promise<boolean>
}>()

const emit = defineEmits<{ (e: 'next'): void }>()

const fields: Array<{ key: TextFieldKey; label: string; required?: boolean; type?: string }> = [
  { key: 'business_name', label: 'Tên cơ sở / thương hiệu', required: true },
  { key: 'legal_name', label: 'Tên pháp lý', required: true },
  { key: 'tax_code', label: 'Mã số thuế' },
  { key: 'representative_name', label: 'Người đại diện', required: true },
  { key: 'id_card_number', label: 'Số CCCD / giấy tờ định danh', required: true },
  { key: 'bank_name', label: 'Ngân hàng nhận tiền', required: true },
  { key: 'bank_code', label: 'Mã ngân hàng', required: true },
  { key: 'bank_account_number', label: 'Số tài khoản', required: true },
  { key: 'bank_account_holder', label: 'Chủ tài khoản', required: true },
  { key: 'contact_name', label: 'Tên liên hệ', required: true },
  { key: 'contact_phone', label: 'Số điện thoại liên hệ', required: true, type: 'tel' },
  { key: 'contact_email', label: 'Email liên hệ', required: true, type: 'email' },
  { key: 'address_line', label: 'Địa chỉ', required: true },
  { key: 'ward', label: 'Phường / xã' },
  { key: 'district', label: 'Quận / huyện' },
  { key: 'city', label: 'Tỉnh / thành phố', required: true },
  { key: 'postal_code', label: 'Mã bưu chính' },
  { key: 'lighting_spec', label: 'Thông tin chiếu sáng' },
]

const values = reactive<Record<TextFieldKey, string>>({
  business_name: '', legal_name: '', tax_code: '', representative_name: '', id_card_number: '',
  bank_name: '', bank_code: '', bank_account_number: '', bank_account_holder: '', contact_name: '',
  contact_phone: '', contact_email: '', address_line: '', ward: '', district: '', city: '',
  postal_code: '', lighting_spec: '',
})
const businessType = ref<BusinessType>('individual')
const sportsInput = ref('')
const courtScaleInput = ref('')
const saveSuccess = ref(false)

function loadApplication(app: PartnerApplication) {
  businessType.value = app.business_type || 'individual'
  for (const field of fields) values[field.key] = app[field.key] || ''
  sportsInput.value = (app.sports || []).join(', ')
  courtScaleInput.value = app.court_scale ? String(app.court_scale) : ''
}

watch(() => props.application, loadApplication, { deep: true, immediate: true })

function buildPayload(): SaveDraftPayload {
  return {
    ...values,
    business_type: businessType.value,
    sports: sportsInput.value.split(',').map((sport) => sport.trim()).filter(Boolean),
    court_scale: courtScaleInput.value ? Number(courtScaleInput.value) : undefined,
    expected_version: props.application.version,
  }
}

async function handleSave() {
  saveSuccess.value = await props.save(buildPayload())
}

async function handleNext() {
  if (await props.save(buildPayload())) emit('next')
}
</script>

<template>
  <div class="business-info-step">
    <div class="step-head">
      <h3 class="step-title">1. Thông tin cơ sở và người đại diện</h3>
      <p class="step-sub">Thông tin được lưu trong hồ sơ để vận hành xác minh và thiết lập tài khoản đối tác.</p>
    </div>

    <p v-if="error" class="error-banner" role="alert">
      <span class="material-symbols-outlined icon-16">error</span>
      <span>{{ error }}</span>
    </p>

    <div class="business-type-group">
      <label class="field-label">Mô hình kinh doanh *</label>
      <div class="business-type-options">
        <label class="radio-pill" :class="{ selected: businessType === 'individual' }">
          <input v-model="businessType" type="radio" value="individual" />
          <span class="radio-dot"></span>
          <span>Cá nhân</span>
        </label>
        <label class="radio-pill" :class="{ selected: businessType === 'household' }">
          <input v-model="businessType" type="radio" value="household" />
          <span class="radio-dot"></span>
          <span>Hộ kinh doanh</span>
        </label>
        <label class="radio-pill" :class="{ selected: businessType === 'company' }">
          <input v-model="businessType" type="radio" value="company" />
          <span class="radio-dot"></span>
          <span>Doanh nghiệp</span>
        </label>
      </div>
      <p v-if="fieldErrors.business_type" class="field-error-msg">{{ fieldErrors.business_type[0] }}</p>
    </div>

    <div class="form-grid">
      <div v-for="field in fields" :key="field.key" class="form-field-item">
        <label :for="field.key" class="field-label">
          {{ field.label }}<span v-if="field.required" class="req-star"> *</span>
        </label>
        <input
          :id="field.key"
          v-model="values[field.key]"
          :type="field.type || 'text'"
          autocomplete="off"
          class="form-input"
        />
        <p v-if="fieldErrors[field.key]" class="field-error-msg">
          {{ fieldErrors[field.key]?.[0] }}
        </p>
      </div>

      <div class="form-field-item">
        <label for="sports" class="field-label">Môn thể thao<span class="req-star"> *</span></label>
        <input id="sports" v-model="sportsInput" type="text" placeholder="Cầu lông, pickleball..." class="form-input" />
        <p v-if="fieldErrors.sports" class="field-error-msg">{{ fieldErrors.sports[0] }}</p>
      </div>

      <div class="form-field-item">
        <label for="courtScale" class="field-label">Số sân dự kiến</label>
        <input id="courtScale" v-model="courtScaleInput" type="number" min="1" max="100" class="form-input" />
        <p v-if="fieldErrors.court_scale" class="field-error-msg">{{ fieldErrors.court_scale[0] }}</p>
      </div>
    </div>

    <div class="step-footer">
      <div class="draft-actions">
        <button type="button" :disabled="saving" class="btn-save-draft" @click="handleSave">
          <span v-if="saving" class="material-symbols-outlined icon-16 spin">sync</span>
          <span>{{ saving ? 'Đang lưu...' : 'Lưu bản nháp' }}</span>
        </button>
        <span v-if="saveSuccess" class="saved-indicator">
          <span class="material-symbols-outlined icon-14">check</span>
          <span>Đã lưu</span>
        </span>
      </div>

      <button type="button" :disabled="saving" class="btn-next-step" @click="handleNext">
        <span>Tiếp tục: Tải tài liệu</span>
        <span class="material-symbols-outlined icon-18">arrow_forward</span>
      </button>
    </div>
  </div>
</template>

<style src="../styles/partner-registration.css"></style>

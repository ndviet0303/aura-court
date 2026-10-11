<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { getActivePinia } from 'pinia'
import { useAuthStore } from '@/features/auth/stores/authStore'
import BusinessAddressSection from './BusinessAddressSection.vue'
import BusinessBankSection from './BusinessBankSection.vue'
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

const POPULAR_SPORTS = ['Cầu lông', 'Pickleball', 'Tennis', 'Bóng bàn', 'Bóng rổ', 'Bóng đá mini']

const values = reactive<Record<TextFieldKey, string>>({
  business_name: '',
  legal_name: '',
  tax_code: '',
  representative_name: '',
  id_card_number: '',
  bank_name: '',
  bank_code: '',
  bank_account_number: '',
  bank_account_holder: '',
  contact_name: '',
  contact_phone: '',
  contact_email: '',
  address_line: '',
  ward: '',
  district: '',
  city: '',
  postal_code: '',
  lighting_spec: '',
})

const businessType = ref<BusinessType>('individual')
const sportsInput = ref('')
const courtScaleInput = ref('')
const saveSuccess = ref(false)
const selectedBankCode = ref('')

function prefill() {
  if (typeof window !== 'undefined') {
    try {
      const raw = localStorage.getItem('aura_onboarding_draft')
      if (raw) {
        const d = JSON.parse(raw)
        if (!values.business_name && d.organizationName) values.business_name = d.organizationName
        if (!values.legal_name && d.organizationName) values.legal_name = d.organizationName
        if (!values.address_line && d.venueAddress) values.address_line = d.venueAddress
        if (!values.contact_phone && d.phone) values.contact_phone = d.phone
        if (!courtScaleInput.value && d.courtsCount) courtScaleInput.value = String(d.courtsCount)
      }
    } catch {}
  }
  if (getActivePinia()) {
    try {
      const u = useAuthStore().user
      if (u) {
        if (!values.representative_name && u.name) values.representative_name = u.name
        if (!values.contact_name && u.name) values.contact_name = u.name
        if (!values.bank_account_holder && u.name) values.bank_account_holder = u.name.toUpperCase()
        if (!values.contact_email && u.email) values.contact_email = u.email
        if (!values.contact_phone && (u as any).phone) values.contact_phone = (u as any).phone
      }
    } catch {}
  }
}

function loadApplication(app: PartnerApplication) {
  businessType.value = app.business_type || 'individual'
  for (const k of Object.keys(values) as TextFieldKey[]) values[k] = app[k] || ''
  sportsInput.value = (app.sports || []).join(', ')
  courtScaleInput.value = app.court_scale ? String(app.court_scale) : ''
  prefill()
  selectedBankCode.value = values.bank_code || ''
}

watch(() => props.application, loadApplication, { deep: true, immediate: true })
onMounted(prefill)

function onBankChange(e: Event) {
  const select = e.target as HTMLSelectElement
  const code = select.value
  selectedBankCode.value = code
  if (code && code !== 'OTHER') {
    values.bank_code = code
    const opt = select.selectedOptions[0]
    if (opt) values.bank_name = opt.text
  } else if (code === 'OTHER') {
    values.bank_code = ''
    values.bank_name = ''
  }
}

const currentSports = computed(() =>
  sportsInput.value
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean),
)

function toggleSport(sport: string) {
  const list = [...currentSports.value]
  const idx = list.findIndex((s) => s.toLowerCase() === sport.toLowerCase())
  if (idx >= 0) list.splice(idx, 1)
  else list.push(sport)
  sportsInput.value = list.join(', ')
}

function isSportSelected(sport: string) {
  return currentSports.value.some((s) => s.toLowerCase() === sport.toLowerCase())
}

function buildPayload(): SaveDraftPayload {
  return {
    ...values,
    business_type: businessType.value,
    sports: sportsInput.value
      .split(',')
      .map((s) => s.trim())
      .filter(Boolean),
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
      <h3 class="step-title">Bước 1: Thông tin Cơ sở & Người đại diện</h3>
      <p class="step-sub">
        Vui lòng hoàn tất 5 mục bên dưới để đội ngũ Aura xác minh và mở cổng vận hành cụm sân.
      </p>
    </div>

    <p v-if="error" class="error-banner" role="alert">
      <span class="material-symbols-outlined icon-16">error</span>
      <span>{{ error }}</span>
    </p>

    <!-- SECTION 1: Thông tin cơ sở & Quy mô -->
    <div class="form-section-card">
      <div class="form-section-header">
        <div class="section-icon-box"><span class="material-symbols-outlined">stadium</span></div>
        <div class="section-title-wrap">
          <h4 class="section-title">1. Thông tin Cơ sở & Quy mô Sân</h4>
          <p class="section-subtitle">
            Tên hiển thị công khai trên ứng dụng và môn thể thao phục vụ
          </p>
        </div>
      </div>
      <div class="business-type-group">
        <label class="field-label">Mô hình kinh doanh <span class="req-star">*</span></label>
        <div class="business-type-options">
          <label class="radio-pill" :class="{ selected: businessType === 'individual' }">
            <input v-model="businessType" type="radio" value="individual" /><span
              class="radio-dot"
            ></span
            ><span>Cá nhân</span>
          </label>
          <label class="radio-pill" :class="{ selected: businessType === 'household' }">
            <input v-model="businessType" type="radio" value="household" /><span
              class="radio-dot"
            ></span
            ><span>Hộ kinh doanh</span>
          </label>
          <label class="radio-pill" :class="{ selected: businessType === 'company' }">
            <input v-model="businessType" type="radio" value="company" /><span
              class="radio-dot"
            ></span
            ><span>Doanh nghiệp</span>
          </label>
        </div>
        <p v-if="fieldErrors.business_type" class="field-error-msg">
          {{ fieldErrors.business_type[0] }}
        </p>
      </div>
      <div class="form-grid">
        <div class="form-field-item form-grid-full">
          <label for="business_name" class="field-label"
            >Tên cơ sở / thương hiệu cụm sân <span class="req-star">*</span></label
          >
          <input
            id="business_name"
            v-model="values.business_name"
            type="text"
            placeholder="Ví dụ: CLB Cầu Lông Aura Arena..."
            autocomplete="organization"
            class="form-input"
          />
          <p v-if="fieldErrors.business_name" class="field-error-msg">
            {{ fieldErrors.business_name[0] }}
          </p>
        </div>
        <div class="form-field-item form-grid-full">
          <label for="sports" class="field-label"
            >Môn thể thao hoạt động <span class="req-star">*</span></label
          >
          <div class="sports-chips-row">
            <span
              v-for="s in POPULAR_SPORTS"
              :key="s"
              role="button"
              tabindex="0"
              class="sport-chip-btn"
              :class="{ selected: isSportSelected(s) }"
              @click="toggleSport(s)"
              @keydown.enter.prevent="toggleSport(s)"
            >
              <span class="material-symbols-outlined icon-14">{{
                isSportSelected(s) ? 'check' : 'add'
              }}</span
              ><span>{{ s }}</span>
            </span>
          </div>
          <input
            id="sports"
            v-model="sportsInput"
            type="text"
            placeholder="Cầu lông, pickleball, tennis..."
            class="form-input"
          />
          <p v-if="fieldErrors.sports" class="field-error-msg">{{ fieldErrors.sports[0] }}</p>
        </div>
        <div class="form-field-item">
          <label for="courtScale" class="field-label">Số sân dự kiến hoạt động</label>
          <input
            id="courtScale"
            v-model="courtScaleInput"
            type="number"
            min="1"
            max="100"
            placeholder="Ví dụ: 4"
            class="form-input"
          />
          <p v-if="fieldErrors.court_scale" class="field-error-msg">
            {{ fieldErrors.court_scale[0] }}
          </p>
        </div>
        <div class="form-field-item">
          <label for="lighting_spec" class="field-label">Thông tin chiếu sáng & tiện ích</label>
          <input
            id="lighting_spec"
            v-model="values.lighting_spec"
            type="text"
            placeholder="Đèn LED chống chói 300 Lux, máy lạnh..."
            class="form-input"
          />
          <p v-if="fieldErrors.lighting_spec" class="field-error-msg">
            {{ fieldErrors.lighting_spec[0] }}
          </p>
        </div>
      </div>
    </div>

    <!-- SECTION 2: Địa chỉ & Vị trí Cơ sở -->
    <BusinessAddressSection :values="values" :field-errors="fieldErrors" />

    <!-- SECTION 3: Người đại diện & Pháp lý -->
    <div class="form-section-card">
      <div class="form-section-header">
        <div class="section-icon-box"><span class="material-symbols-outlined">badge</span></div>
        <div class="section-title-wrap">
          <h4 class="section-title">3. Người đại diện & Pháp lý</h4>
          <p class="section-subtitle">
            Thông tin định danh để bảo đảm tính pháp lý và an toàn giao dịch
          </p>
        </div>
      </div>
      <div class="form-grid">
        <div class="form-field-item">
          <label for="representative_name" class="field-label"
            >Họ và tên người đại diện <span class="req-star">*</span></label
          >
          <input
            id="representative_name"
            v-model="values.representative_name"
            type="text"
            placeholder="Nguyễn Văn A"
            autocomplete="name"
            class="form-input"
          />
          <p v-if="fieldErrors.representative_name" class="field-error-msg">
            {{ fieldErrors.representative_name[0] }}
          </p>
        </div>
        <div class="form-field-item">
          <label for="id_card_number" class="field-label"
            >Số CCCD / giấy tờ định danh <span class="req-star">*</span></label
          >
          <input
            id="id_card_number"
            v-model="values.id_card_number"
            type="text"
            placeholder="Số thẻ căn cước 12 số"
            class="form-input"
          />
          <p v-if="fieldErrors.id_card_number" class="field-error-msg">
            {{ fieldErrors.id_card_number[0] }}
          </p>
        </div>
        <div class="form-field-item">
          <label for="legal_name" class="field-label"
            >Tên pháp lý đăng ký kinh doanh <span class="req-star">*</span></label
          >
          <input
            id="legal_name"
            v-model="values.legal_name"
            type="text"
            placeholder="Tên cá nhân hoặc Cty TNHH..."
            class="form-input"
          />
          <p v-if="fieldErrors.legal_name" class="field-error-msg">
            {{ fieldErrors.legal_name[0] }}
          </p>
        </div>
        <div class="form-field-item">
          <label for="tax_code" class="field-label">Mã số thuế</label>
          <input
            id="tax_code"
            v-model="values.tax_code"
            type="text"
            placeholder="Mã số thuế (nếu có)"
            class="form-input"
          />
          <p v-if="fieldErrors.tax_code" class="field-error-msg">{{ fieldErrors.tax_code[0] }}</p>
        </div>
      </div>
    </div>

    <!-- SECTION 4: Tài khoản Ngân hàng nhận thanh toán -->
    <BusinessBankSection
      :values="values"
      :field-errors="fieldErrors"
      :selected-bank-code="selectedBankCode"
      @bank-change="onBankChange"
    />

    <!-- SECTION 5: Người liên hệ vận hành -->
    <div class="form-section-card">
      <div class="form-section-header">
        <div class="section-icon-box">
          <span class="material-symbols-outlined">contact_phone</span>
        </div>
        <div class="section-title-wrap">
          <h4 class="section-title">5. Người liên hệ vận hành</h4>
          <p class="section-subtitle">
            Kênh liên lạc trực tiếp khi cần hỗ trợ khách hàng hoặc thông báo khẩn cấp
          </p>
        </div>
      </div>
      <div class="form-grid">
        <div class="form-field-item form-grid-full">
          <label for="contact_name" class="field-label"
            >Tên người liên hệ <span class="req-star">*</span></label
          >
          <input
            id="contact_name"
            v-model="values.contact_name"
            type="text"
            placeholder="Họ và tên người phụ trách"
            autocomplete="name"
            class="form-input"
          />
          <p v-if="fieldErrors.contact_name" class="field-error-msg">
            {{ fieldErrors.contact_name[0] }}
          </p>
        </div>
        <div class="form-field-item">
          <label for="contact_phone" class="field-label"
            >Số điện thoại hotline <span class="req-star">*</span></label
          >
          <input
            id="contact_phone"
            v-model="values.contact_phone"
            type="tel"
            placeholder="0987654321"
            autocomplete="tel"
            class="form-input"
          />
          <p v-if="fieldErrors.contact_phone" class="field-error-msg">
            {{ fieldErrors.contact_phone[0] }}
          </p>
        </div>
        <div class="form-field-item">
          <label for="contact_email" class="field-label"
            >Email liên hệ <span class="req-star">*</span></label
          >
          <input
            id="contact_email"
            v-model="values.contact_email"
            type="email"
            placeholder="hotline@sanclb.vn"
            autocomplete="email"
            class="form-input"
          />
          <p v-if="fieldErrors.contact_email" class="field-error-msg">
            {{ fieldErrors.contact_email[0] }}
          </p>
        </div>
      </div>
    </div>

    <!-- Footer Actions -->
    <div class="step-footer">
      <div class="draft-actions">
        <button type="button" :disabled="saving" class="btn-save-draft" @click="handleSave">
          <span v-if="saving" class="material-symbols-outlined icon-16 spin">sync</span>
          <span>{{ saving ? 'Đang lưu...' : 'Lưu bản nháp' }}</span>
        </button>
        <span v-if="saveSuccess" class="saved-indicator">
          <span class="material-symbols-outlined icon-14">check</span>
          <span>Đã lưu thành công</span>
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

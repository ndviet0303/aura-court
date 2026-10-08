<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { getActivePinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'
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

function getAuthUser() {
  if (getActivePinia()) {
    try {
      return useAuthStore().user
    } catch {}
  }
  return null
}
const POPULAR_BANKS = [
  { code: 'MB', name: 'MBBank (Ngân hàng Quân Đội)' },
  { code: 'VCB', name: 'Vietcombank (Ngoại Thương Việt Nam)' },
  { code: 'TCB', name: 'Techcombank (Kỹ Thương Việt Nam)' },
  { code: 'ACB', name: 'ACB (Ngân hàng Á Châu)' },
  { code: 'VPB', name: 'VPBank (Việt Nam Thịnh Vượng)' },
  { code: 'BIDV', name: 'BIDV (Đầu tư & Phát triển Việt Nam)' },
  { code: 'CTG', name: 'VietinBank (Công Thương Việt Nam)' },
  { code: 'TPB', name: 'TPBank (Tiên Phong)' },
  { code: 'HDB', name: 'HDBank (Phát triển TP.HCM)' },
  { code: 'VIB', name: 'VIB (Quốc Tế Việt Nam)' },
  { code: 'STB', name: 'Sacombank (Sài Gòn Thương Tín)' },
  { code: 'SHB', name: 'SHB (Sài Gòn - Hà Nội)' },
  { code: 'MSB', name: 'MSB (Hàng Hải Việt Nam)' },
  { code: 'OCB', name: 'OCB (Phương Đông)' },
  { code: 'LPB', name: 'LPBank (Lộc Phát Việt Nam)' },
  { code: 'AGRIBANK', name: 'Agribank (Nông Nghiệp & PT Nông Thôn)' },
]

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

function prefillFromOnboardingAndUser() {
  if (typeof window !== 'undefined') {
    try {
      const rawDraft = localStorage.getItem('aura_onboarding_draft')
      if (rawDraft) {
        const draft = JSON.parse(rawDraft)
        if (!values.business_name && draft.organizationName) values.business_name = draft.organizationName
        if (!values.legal_name && draft.organizationName) values.legal_name = draft.organizationName
        if (!values.address_line && draft.venueAddress) values.address_line = draft.venueAddress
        if (!values.contact_phone && draft.phone) values.contact_phone = draft.phone
        if (!courtScaleInput.value && draft.courtsCount) courtScaleInput.value = String(draft.courtsCount)
      }
    } catch {}
  }

  const u = getAuthUser()
  if (u) {
    if (!values.representative_name && u.name) values.representative_name = u.name
    if (!values.contact_name && u.name) values.contact_name = u.name
    if (!values.bank_account_holder && u.name) values.bank_account_holder = u.name.toUpperCase()
    if (!values.contact_email && u.email) values.contact_email = u.email
    if (!values.contact_phone && (u as any).phone) values.contact_phone = (u as any).phone
  }
}

function loadApplication(app: PartnerApplication) {
  businessType.value = app.business_type || 'individual'
  for (const k of Object.keys(values) as TextFieldKey[]) {
    values[k] = app[k] || ''
  }
  sportsInput.value = (app.sports || []).join(', ')
  courtScaleInput.value = app.court_scale ? String(app.court_scale) : ''

  // Pre-fill from draft & profile if still empty
  prefillFromOnboardingAndUser()

  // Match existing bank
  const matched = POPULAR_BANKS.find(
    (b) => b.code.toUpperCase() === values.bank_code?.toUpperCase() || (values.bank_name && values.bank_name.includes(b.code))
  )
  selectedBankCode.value = matched?.code || (values.bank_code ? 'OTHER' : '')
}

watch(() => props.application, loadApplication, { deep: true, immediate: true })

onMounted(() => {
  prefillFromOnboardingAndUser()
})

function onBankSelectChange(e: Event) {
  const code = (e.target as HTMLSelectElement).value
  selectedBankCode.value = code
  if (code && code !== 'OTHER') {
    const found = POPULAR_BANKS.find((b) => b.code === code)
    if (found) {
      values.bank_code = found.code
      values.bank_name = found.name
    }
  } else if (code === 'OTHER') {
    values.bank_code = ''
    values.bank_name = ''
  }
}

const currentSportsList = computed(() => {
  return sportsInput.value
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean)
})

function toggleSport(sport: string) {
  const current = [...currentSportsList.value]
  const idx = current.findIndex((s) => s.toLowerCase() === sport.toLowerCase())
  if (idx >= 0) {
    current.splice(idx, 1)
  } else {
    current.push(sport)
  }
  sportsInput.value = current.join(', ')
}

function isSportSelected(sport: string): boolean {
  return currentSportsList.value.some((s) => s.toLowerCase() === sport.toLowerCase())
}

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
      <h3 class="step-title">Bước 1: Thông tin Cơ sở & Người đại diện</h3>
      <p class="step-sub">Vui lòng hoàn tất 5 mục bên dưới để đội ngũ Aura xác minh và mở cổng vận hành cụm sân.</p>
    </div>

    <p v-if="error" class="error-banner" role="alert">
      <span class="material-symbols-outlined icon-16">error</span>
      <span>{{ error }}</span>
    </p>

    <!-- SECTION 1: Thông tin cơ sở & Quy mô -->
    <div class="form-section-card">
      <div class="form-section-header">
        <div class="section-icon-box">
          <span class="material-symbols-outlined">stadium</span>
        </div>
        <div class="section-title-wrap">
          <h4 class="section-title">1. Thông tin Cơ sở & Quy mô Sân</h4>
          <p class="section-subtitle">Tên hiển thị công khai trên ứng dụng và môn thể thao phục vụ</p>
        </div>
      </div>

      <div class="business-type-group">
        <label class="field-label">Mô hình kinh doanh <span class="req-star">*</span></label>
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
        <div class="form-field-item form-grid-full">
          <label for="business_name" class="field-label">
            Tên cơ sở / thương hiệu cụm sân <span class="req-star">*</span>
          </label>
          <input
            id="business_name"
            v-model="values.business_name"
            type="text"
            placeholder="Ví dụ: CLB Cầu Lông Aura Arena, Sân Cầu Lông Smash City..."
            autocomplete="organization"
            class="form-input"
          />
          <p v-if="fieldErrors.business_name" class="field-error-msg">{{ fieldErrors.business_name[0] }}</p>
        </div>

        <div class="form-field-item form-grid-full">
          <label for="sports" class="field-label">
            Môn thể thao hoạt động <span class="req-star">*</span>
          </label>
          <div class="sports-chips-row">
            <span
              v-for="sport in POPULAR_SPORTS"
              :key="sport"
              role="button"
              tabindex="0"
              class="sport-chip-btn"
              :class="{ selected: isSportSelected(sport) }"
              @click="toggleSport(sport)"
              @keydown.enter.prevent="toggleSport(sport)"
              @keydown.space.prevent="toggleSport(sport)"
            >
              <span class="material-symbols-outlined icon-14">{{ isSportSelected(sport) ? 'check' : 'add' }}</span>
              <span>{{ sport }}</span>
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
          <p v-if="fieldErrors.court_scale" class="field-error-msg">{{ fieldErrors.court_scale[0] }}</p>
        </div>

        <div class="form-field-item">
          <label for="lighting_spec" class="field-label">Thông tin chiếu sáng & tiện ích</label>
          <input
            id="lighting_spec"
            v-model="values.lighting_spec"
            type="text"
            placeholder="Đèn LED chống chói 300 Lux, máy lạnh, thảm BWF..."
            class="form-input"
          />
          <p v-if="fieldErrors.lighting_spec" class="field-error-msg">{{ fieldErrors.lighting_spec[0] }}</p>
        </div>
      </div>
    </div>

    <!-- SECTION 2: Địa chỉ & Vị trí Cơ sở -->
    <div class="form-section-card">
      <div class="form-section-header">
        <div class="section-icon-box">
          <span class="material-symbols-outlined">location_on</span>
        </div>
        <div class="section-title-wrap">
          <h4 class="section-title">2. Địa chỉ & Vị trí Cụm sân</h4>
          <p class="section-subtitle">Vị trí thực tế để khách hàng tìm đường và định vị trên bản đồ</p>
        </div>
      </div>

      <div class="form-grid">
        <div class="form-field-item form-grid-full">
          <label for="address_line" class="field-label">
            Địa chỉ chi tiết (Số nhà, tên đường) <span class="req-star">*</span>
          </label>
          <input
            id="address_line"
            v-model="values.address_line"
            type="text"
            placeholder="Ví dụ: 123 Kha Vạn Cân, Phường Linh Chiểu"
            autocomplete="street-address"
            class="form-input"
          />
          <p v-if="fieldErrors.address_line" class="field-error-msg">{{ fieldErrors.address_line[0] }}</p>
        </div>

        <div class="form-field-item">
          <label for="ward" class="field-label">Phường / Xã</label>
          <input id="ward" v-model="values.ward" type="text" placeholder="Phường Linh Chiểu" class="form-input" />
          <p v-if="fieldErrors.ward" class="field-error-msg">{{ fieldErrors.ward[0] }}</p>
        </div>

        <div class="form-field-item">
          <label for="district" class="field-label">Quận / Huyện</label>
          <input id="district" v-model="values.district" type="text" placeholder="Thành phố Thủ Đức" class="form-input" />
          <p v-if="fieldErrors.district" class="field-error-msg">{{ fieldErrors.district[0] }}</p>
        </div>

        <div class="form-field-item">
          <label for="city" class="field-label">
            Tỉnh / Thành phố <span class="req-star">*</span>
          </label>
          <input
            id="city"
            v-model="values.city"
            type="text"
            placeholder="TP. Hồ Chí Minh"
            autocomplete="address-level1"
            class="form-input"
          />
          <p v-if="fieldErrors.city" class="field-error-msg">{{ fieldErrors.city[0] }}</p>
        </div>

        <div class="form-field-item">
          <label for="postal_code" class="field-label">Mã bưu chính</label>
          <input id="postal_code" v-model="values.postal_code" type="text" placeholder="700000" class="form-input" />
          <p v-if="fieldErrors.postal_code" class="field-error-msg">{{ fieldErrors.postal_code[0] }}</p>
        </div>
      </div>
    </div>

    <!-- SECTION 3: Người đại diện & Pháp lý -->
    <div class="form-section-card">
      <div class="form-section-header">
        <div class="section-icon-box">
          <span class="material-symbols-outlined">badge</span>
        </div>
        <div class="section-title-wrap">
          <h4 class="section-title">3. Người đại diện & Pháp lý</h4>
          <p class="section-subtitle">Thông tin định danh để bảo đảm tính pháp lý và an toàn giao dịch</p>
        </div>
      </div>

      <div class="form-grid">
        <div class="form-field-item">
          <label for="representative_name" class="field-label">
            Họ và tên người đại diện <span class="req-star">*</span>
          </label>
          <input
            id="representative_name"
            v-model="values.representative_name"
            type="text"
            placeholder="Nguyễn Văn A"
            autocomplete="name"
            class="form-input"
          />
          <p v-if="fieldErrors.representative_name" class="field-error-msg">{{ fieldErrors.representative_name[0] }}</p>
        </div>

        <div class="form-field-item">
          <label for="id_card_number" class="field-label">
            Số CCCD / giấy tờ định danh <span class="req-star">*</span>
          </label>
          <input
            id="id_card_number"
            v-model="values.id_card_number"
            type="text"
            placeholder="Số thẻ căn cước 12 số"
            class="form-input"
          />
          <p v-if="fieldErrors.id_card_number" class="field-error-msg">{{ fieldErrors.id_card_number[0] }}</p>
        </div>

        <div class="form-field-item">
          <label for="legal_name" class="field-label">
            Tên pháp lý đăng ký kinh doanh <span class="req-star">*</span>
          </label>
          <input
            id="legal_name"
            v-model="values.legal_name"
            type="text"
            placeholder="Tên cá nhân hoặc Cty TNHH..."
            class="form-input"
          />
          <p v-if="fieldErrors.legal_name" class="field-error-msg">{{ fieldErrors.legal_name[0] }}</p>
        </div>

        <div class="form-field-item">
          <label for="tax_code" class="field-label">Mã số thuế</label>
          <input id="tax_code" v-model="values.tax_code" type="text" placeholder="Mã số thuế (nếu có)" class="form-input" />
          <p v-if="fieldErrors.tax_code" class="field-error-msg">{{ fieldErrors.tax_code[0] }}</p>
        </div>
      </div>
    </div>

    <!-- SECTION 4: Tài khoản Ngân hàng nhận thanh toán -->
    <div class="form-section-card">
      <div class="form-section-header">
        <div class="section-icon-box">
          <span class="material-symbols-outlined">account_balance</span>
        </div>
        <div class="section-title-wrap">
          <h4 class="section-title">4. Tài khoản Ngân hàng nhận tiền (VietQR)</h4>
          <p class="section-subtitle">Doanh thu khách đặt sân sẽ được đối soát và chuyển trực tiếp về tài khoản này</p>
        </div>
      </div>

      <div class="form-grid">
        <div class="form-field-item form-grid-full">
          <label for="bank_select" class="field-label">
            Chọn ngân hàng nhận tiền <span class="req-star">*</span>
          </label>
          <select id="bank_select" :value="selectedBankCode" class="form-select" @change="onBankSelectChange">
            <option value="" disabled>-- Chọn ngân hàng thụ hưởng --</option>
            <option v-for="bank in POPULAR_BANKS" :key="bank.code" :value="bank.code">
              {{ bank.name }}
            </option>
            <option value="OTHER">Ngân hàng khác (Tự nhập thủ công)</option>
          </select>
        </div>

        <div class="form-field-item">
          <label for="bank_name" class="field-label">
            Tên ngân hàng <span class="req-star">*</span>
          </label>
          <input
            id="bank_name"
            v-model="values.bank_name"
            type="text"
            placeholder="Ví dụ: MBBank"
            class="form-input"
          />
          <p v-if="fieldErrors.bank_name" class="field-error-msg">{{ fieldErrors.bank_name[0] }}</p>
        </div>

        <div class="form-field-item">
          <label for="bank_code" class="field-label">
            Mã ngân hàng (Bank Code) <span class="req-star">*</span>
          </label>
          <input
            id="bank_code"
            v-model="values.bank_code"
            type="text"
            placeholder="MB, VCB, TCB..."
            class="form-input"
          />
          <p v-if="fieldErrors.bank_code" class="field-error-msg">{{ fieldErrors.bank_code[0] }}</p>
        </div>

        <div class="form-field-item">
          <label for="bank_account_number" class="field-label">
            Số tài khoản ngân hàng <span class="req-star">*</span>
          </label>
          <input
            id="bank_account_number"
            v-model="values.bank_account_number"
            type="text"
            placeholder="Nhập số tài khoản nhận tiền"
            class="form-input"
          />
          <p v-if="fieldErrors.bank_account_number" class="field-error-msg">{{ fieldErrors.bank_account_number[0] }}</p>
        </div>

        <div class="form-field-item">
          <label for="bank_account_holder" class="field-label">
            Tên chủ tài khoản (in hoa không dấu) <span class="req-star">*</span>
          </label>
          <input
            id="bank_account_holder"
            v-model="values.bank_account_holder"
            type="text"
            placeholder="NGUYEN VAN A"
            class="form-input"
            @input="values.bank_account_holder = values.bank_account_holder.toUpperCase()"
          />
          <p v-if="fieldErrors.bank_account_holder" class="field-error-msg">{{ fieldErrors.bank_account_holder[0] }}</p>
        </div>
      </div>
    </div>

    <!-- SECTION 5: Người liên hệ vận hành -->
    <div class="form-section-card">
      <div class="form-section-header">
        <div class="section-icon-box">
          <span class="material-symbols-outlined">contact_phone</span>
        </div>
        <div class="section-title-wrap">
          <h4 class="section-title">5. Người liên hệ vận hành</h4>
          <p class="section-subtitle">Kênh liên lạc trực tiếp khi cần hỗ trợ khách hàng hoặc thông báo khẩn cấp</p>
        </div>
      </div>

      <div class="form-grid">
        <div class="form-field-item form-grid-full">
          <label for="contact_name" class="field-label">
            Tên người liên hệ <span class="req-star">*</span>
          </label>
          <input
            id="contact_name"
            v-model="values.contact_name"
            type="text"
            placeholder="Họ và tên người phụ trách"
            autocomplete="name"
            class="form-input"
          />
          <p v-if="fieldErrors.contact_name" class="field-error-msg">{{ fieldErrors.contact_name[0] }}</p>
        </div>

        <div class="form-field-item">
          <label for="contact_phone" class="field-label">
            Số điện thoại hotline <span class="req-star">*</span>
          </label>
          <input
            id="contact_phone"
            v-model="values.contact_phone"
            type="tel"
            placeholder="0987654321"
            autocomplete="tel"
            class="form-input"
          />
          <p v-if="fieldErrors.contact_phone" class="field-error-msg">{{ fieldErrors.contact_phone[0] }}</p>
        </div>

        <div class="form-field-item">
          <label for="contact_email" class="field-label">
            Email liên hệ <span class="req-star">*</span>
          </label>
          <input
            id="contact_email"
            v-model="values.contact_email"
            type="email"
            placeholder="hotline@sanclb.vn"
            autocomplete="email"
            class="form-input"
          />
          <p v-if="fieldErrors.contact_email" class="field-error-msg">{{ fieldErrors.contact_email[0] }}</p>
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

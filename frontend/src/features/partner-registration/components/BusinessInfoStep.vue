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
  <div class="business-info-step space-y-6">
    <div>
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">1. Thông tin cơ sở và người đại diện</h3>
      <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Thông tin được lưu trong hồ sơ để vận hành xác minh.</p>
    </div>

    <p v-if="error" class="rounded-lg bg-red-50 p-3 text-xs text-red-700 dark:bg-red-950/20 dark:text-red-400" role="alert">
      {{ error }}
    </p>

    <div>
      <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Mô hình kinh doanh</span>
      <div class="flex flex-wrap gap-4 text-sm text-gray-800 dark:text-gray-200">
        <label><input v-model="businessType" type="radio" value="individual" /> Cá nhân</label>
        <label><input v-model="businessType" type="radio" value="household" /> Hộ kinh doanh</label>
        <label><input v-model="businessType" type="radio" value="company" /> Doanh nghiệp</label>
      </div>
      <p v-if="fieldErrors.business_type" class="mt-1 text-xs text-red-600">{{ fieldErrors.business_type[0] }}</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <div v-for="field in fields" :key="field.key">
        <label :for="field.key" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
          {{ field.label }}<span v-if="field.required" class="text-red-500"> *</span>
        </label>
        <input
          :id="field.key"
          v-model="values[field.key]"
          :type="field.type || 'text'"
          autocomplete="off"
          class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
        />
        <p v-if="fieldErrors[field.key]" class="mt-1 text-xs text-red-600 dark:text-red-400">
          {{ fieldErrors[field.key]?.[0] }}
        </p>
      </div>
      <div>
        <label for="sports" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Môn thể thao *</label>
        <input id="sports" v-model="sportsInput" type="text" placeholder="Cầu lông, pickleball" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900" />
        <p v-if="fieldErrors.sports" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ fieldErrors.sports[0] }}</p>
      </div>
      <div>
        <label for="courtScale" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Số sân dự kiến</label>
        <input id="courtScale" v-model="courtScaleInput" type="number" min="1" max="100" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900" />
        <p v-if="fieldErrors.court_scale" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ fieldErrors.court_scale[0] }}</p>
      </div>
    </div>

    <div class="flex items-center justify-between border-t pt-4 dark:border-gray-700">
      <div>
        <button type="button" :disabled="saving" class="rounded-lg bg-gray-100 px-4 py-2 text-sm text-gray-700 disabled:opacity-50 dark:bg-gray-700 dark:text-gray-200" @click="handleSave">
          {{ saving ? 'Đang lưu...' : 'Lưu bản nháp' }}
        </button>
        <span v-if="saveSuccess" class="ml-2 text-xs text-emerald-700">Đã lưu</span>
      </div>
      <button type="button" :disabled="saving" class="rounded-lg bg-emerald-600 px-6 py-2 text-sm font-medium text-white disabled:opacity-50" @click="handleNext">
        Tiếp tục: Tải tài liệu
      </button>
    </div>
  </div>
</template>

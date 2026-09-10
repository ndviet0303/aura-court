<script setup lang="ts">
import { ref, watch } from 'vue'
import type { BusinessType, PartnerApplication, SaveDraftPayload } from '../types'

const props = defineProps<{
  application: PartnerApplication
  fieldErrors: Record<string, string[]>
  saving: boolean
}>()

const emit = defineEmits<{
  (e: 'save', payload: SaveDraftPayload): void
  (e: 'next'): void
}>()

const businessType = ref<BusinessType>(props.application.business_type || 'individual')
const businessName = ref(props.application.business_name || '')
const taxCode = ref(props.application.tax_code || '')
const contactName = ref(props.application.contact_name || '')
const contactPhone = ref(props.application.contact_phone || '')
const contactEmail = ref(props.application.contact_email || '')
const saveSuccess = ref(false)

watch(
  () => props.application,
  (app) => {
    if (app) {
      if (app.business_type) businessType.value = app.business_type
      if (app.business_name) businessName.value = app.business_name
      if (app.tax_code) taxCode.value = app.tax_code
      if (app.contact_name) contactName.value = app.contact_name
      if (app.contact_phone) contactPhone.value = app.contact_phone
      if (app.contact_email) contactEmail.value = app.contact_email
    }
  },
  { deep: true }
)

function buildPayload(): SaveDraftPayload {
  return {
    business_type: businessType.value,
    business_name: businessName.value.trim() || undefined,
    tax_code: taxCode.value.trim() || undefined,
    contact_name: contactName.value.trim() || undefined,
    contact_phone: contactPhone.value.trim() || undefined,
    contact_email: contactEmail.value.trim() || undefined,
  }
}

function handleSave() {
  saveSuccess.value = false
  emit('save', buildPayload())
}

function handleNext() {
  handleSave()
  emit('next')
}
</script>

<template>
  <div class="business-info-step space-y-6">
    <div>
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">
        1. Hình thức và Thông tin Kinh doanh
      </h3>
      <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
        Chọn mô hình hoạt động và cung cấp thông tin pháp nhân hoặc chủ hộ kinh doanh.
      </p>
    </div>

    <!-- Business Type Selector -->
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
        Mô hình kinh doanh <span class="text-red-500">*</span>
      </label>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <label
          class="flex flex-col p-3 border rounded-lg cursor-pointer transition-colors"
          :class="businessType === 'individual' ? 'border-emerald-600 bg-emerald-50/40 dark:bg-emerald-950/20' : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800'"
        >
          <div class="flex items-center gap-2">
            <input v-model="businessType" type="radio" value="individual" class="text-emerald-600" />
            <span class="text-sm font-semibold text-gray-900 dark:text-white">Cá nhân</span>
          </div>
          <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">Chủ sân cá nhân cho thuê sân tự do.</span>
        </label>

        <label
          class="flex flex-col p-3 border rounded-lg cursor-pointer transition-colors"
          :class="businessType === 'household' ? 'border-emerald-600 bg-emerald-50/40 dark:bg-emerald-950/20' : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800'"
        >
          <div class="flex items-center gap-2">
            <input v-model="businessType" type="radio" value="household" class="text-emerald-600" />
            <span class="text-sm font-semibold text-gray-900 dark:text-white">Hộ kinh doanh</span>
          </div>
          <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">Có giấy phép đăng ký hộ kinh doanh cá thể.</span>
        </label>

        <label
          class="flex flex-col p-3 border rounded-lg cursor-pointer transition-colors"
          :class="businessType === 'enterprise' ? 'border-emerald-600 bg-emerald-50/40 dark:bg-emerald-950/20' : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800'"
        >
          <div class="flex items-center gap-2">
            <input v-model="businessType" type="radio" value="enterprise" class="text-emerald-600" />
            <span class="text-sm font-semibold text-gray-900 dark:text-white">Doanh nghiệp</span>
          </div>
          <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">Công ty TNHH, Cổ phần, Doanh nghiệp tư nhân.</span>
        </label>
      </div>
      <p v-if="fieldErrors.business_type" class="text-xs text-red-600 dark:text-red-400 mt-1">
        {{ fieldErrors.business_type[0] }}
      </p>
    </div>

    <!-- Business Name & Tax Code -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div>
        <label for="businessName" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          Tên doanh nghiệp / Tên sân thương hiệu <span class="text-red-500">*</span>
        </label>
        <input
          id="businessName"
          v-model="businessName"
          type="text"
          placeholder="Ví dụ: Công ty TNHH Thể Thao ABC"
          class="w-full px-3 py-2 border rounded-lg text-sm bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white focus:ring-emerald-500 focus:border-emerald-500"
        />
        <p v-if="fieldErrors.business_name" class="text-xs text-red-600 dark:text-red-400 mt-1">
          {{ fieldErrors.business_name[0] }}
        </p>
      </div>

      <div>
        <label for="taxCode" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          Mã số thuế {{ businessType !== 'individual' ? '*' : '(nếu có)' }}
        </label>
        <input
          id="taxCode"
          v-model="taxCode"
          type="text"
          placeholder="Ví dụ: 0101234567"
          class="w-full px-3 py-2 border rounded-lg text-sm bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white focus:ring-emerald-500 focus:border-emerald-500"
        />
        <p v-if="fieldErrors.tax_code" class="text-xs text-red-600 dark:text-red-400 mt-1">
          {{ fieldErrors.tax_code[0] }}
        </p>
      </div>
    </div>

    <!-- Contact Details -->
    <div class="border-t pt-4 dark:border-gray-700">
      <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">
        Thông tin người đại diện liên hệ
      </h4>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label for="contactName" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
            Họ và tên <span class="text-red-500">*</span>
          </label>
          <input
            id="contactName"
            v-model="contactName"
            type="text"
            placeholder="Nguyễn Văn A"
            class="w-full px-3 py-2 border rounded-lg text-sm bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white"
          />
          <p v-if="fieldErrors.contact_name" class="text-xs text-red-600 dark:text-red-400 mt-1">
            {{ fieldErrors.contact_name[0] }}
          </p>
        </div>

        <div>
          <label for="contactPhone" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
            Số điện thoại liên hệ <span class="text-red-500">*</span>
          </label>
          <input
            id="contactPhone"
            v-model="contactPhone"
            type="tel"
            placeholder="0987654321"
            class="w-full px-3 py-2 border rounded-lg text-sm bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white"
          />
          <p v-if="fieldErrors.contact_phone" class="text-xs text-red-600 dark:text-red-400 mt-1">
            {{ fieldErrors.contact_phone[0] }}
          </p>
        </div>

        <div>
          <label for="contactEmail" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
            Email nhận thông báo <span class="text-red-500">*</span>
          </label>
          <input
            id="contactEmail"
            v-model="contactEmail"
            type="email"
            placeholder="partner@example.com"
            class="w-full px-3 py-2 border rounded-lg text-sm bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white"
          />
          <p v-if="fieldErrors.contact_email" class="text-xs text-red-600 dark:text-red-400 mt-1">
            {{ fieldErrors.contact_email[0] }}
          </p>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-between border-t pt-4 dark:border-gray-700">
      <button
        type="button"
        :disabled="saving"
        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg dark:bg-gray-700 dark:text-gray-200 disabled:opacity-50"
        @click="handleSave"
      >
        {{ saving ? 'Đang lưu...' : 'Lưu bản nháp' }}
      </button>

      <button
        type="button"
        :disabled="saving"
        class="px-6 py-2 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg disabled:opacity-50"
        @click="handleNext"
      >
        Tiếp tục: Tải tài liệu
      </button>
    </div>
  </div>
</template>

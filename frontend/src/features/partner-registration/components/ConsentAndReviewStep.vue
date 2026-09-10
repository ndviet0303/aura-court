<script setup lang="ts">
import { ref } from 'vue'
import type { PartnerApplication, SubmitApplicationPayload } from '../types'

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

const consentPartner = ref(false)
const consentData = ref(false)
const localError = ref('')

function handleSubmit() {
  localError.value = ''
  if (!consentPartner.value || !consentData.value) {
    localError.value = 'Vui lòng xác nhận toàn bộ cam kết và chính sách trước khi nộp hồ sơ.'
    return
  }

  const payload: SubmitApplicationPayload = {
    contact_name: props.application.contact_name || '',
    contact_phone: props.application.contact_phone || '',
    contact_email: props.application.contact_email || '',
    consent_partner: consentPartner.value,
    consent_data: consentData.value,
    terms_version: 'v1.0',
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
  <div class="consent-and-review-step space-y-6">
    <div>
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">
        3. Rà soát Hồ sơ & Xác nhận Cam kết
      </h3>
      <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
        Kiểm tra lại toàn bộ thông tin đã nhập trước khi chính thức nộp vào hàng đợi xét duyệt.
      </p>
    </div>

    <!-- Review Summary Card -->
    <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-5 border border-gray-200 dark:border-gray-700 space-y-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
        <div>
          <span class="text-xs text-gray-500 dark:text-gray-400 block">Mô hình kinh doanh</span>
          <span class="font-medium text-gray-900 dark:text-white capitalize">
            {{ application.business_type === 'enterprise' ? 'Doanh nghiệp' : application.business_type === 'household' ? 'Hộ kinh doanh' : 'Cá nhân' }}
          </span>
        </div>
        <div>
          <span class="text-xs text-gray-500 dark:text-gray-400 block">Tên cơ sở / Doanh nghiệp</span>
          <span class="font-medium text-gray-900 dark:text-white">
            {{ application.business_name || 'Chưa cung cấp' }}
          </span>
        </div>
        <div>
          <span class="text-xs text-gray-500 dark:text-gray-400 block">Mã số thuế</span>
          <span class="font-medium text-gray-900 dark:text-white">
            {{ maskTaxCode(application.tax_code) }}
          </span>
        </div>
        <div>
          <span class="text-xs text-gray-500 dark:text-gray-400 block">Người đại diện liên hệ</span>
          <span class="font-medium text-gray-900 dark:text-white">
            {{ application.contact_name || 'Chưa cung cấp' }} ({{ application.contact_phone || 'N/A' }})
          </span>
        </div>
      </div>

      <div class="border-t pt-3 dark:border-gray-700">
        <span class="text-xs text-gray-500 dark:text-gray-400 block mb-2">Tài liệu đã tải lên ({{ application.documents?.length || 0 }} tệp)</span>
        <ul class="text-xs space-y-1 text-gray-700 dark:text-gray-300">
          <li v-for="doc in application.documents" :key="doc.id" class="flex items-center gap-1.5">
            <span class="material-symbols-outlined text-sm text-emerald-600">check</span>
            <span class="font-medium">{{ doc.original_name }}</span>
            <span class="text-gray-400">({{ doc.document_type }})</span>
          </li>
        </ul>
      </div>
    </div>

    <!-- Versioned Consents -->
    <div class="space-y-3 border-t pt-4 dark:border-gray-700">
      <label class="flex items-start gap-3 cursor-pointer">
        <input
          v-model="consentPartner"
          type="checkbox"
          class="mt-1 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
        />
        <span class="text-sm text-gray-700 dark:text-gray-200">
          Tôi cam kết mọi thông tin và chứng từ cung cấp là chính xác, hợp pháp và chịu hoàn toàn trách nhiệm trước pháp luật.
        </span>
      </label>

      <label class="flex items-start gap-3 cursor-pointer">
        <input
          v-model="consentData"
          type="checkbox"
          class="mt-1 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
        />
        <span class="text-sm text-gray-700 dark:text-gray-200">
          Tôi đồng ý để Aura Sports xác thực và đối chiếu thông tin pháp nhân/sở hữu mặt bằng với cơ quan thẩm quyền hoặc đơn vị liên quan.
        </span>
      </label>
    </div>

    <div v-if="localError || error" class="text-xs text-red-600 dark:text-red-400">
      {{ localError || error }}
    </div>

    <!-- Navigation Buttons -->
    <div class="flex items-center justify-between border-t pt-4 dark:border-gray-700">
      <button
        type="button"
        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg dark:bg-gray-700 dark:text-gray-200"
        @click="emit('back')"
      >
        Quay lại
      </button>

      <button
        type="button"
        :disabled="submitting"
        class="px-6 py-2.5 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg disabled:opacity-50 flex items-center gap-2"
        @click="handleSubmit"
      >
        <span v-if="submitting" class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></span>
        {{ submitting ? 'Đang gửi duyệt...' : 'Nộp Hồ sơ Xét duyệt' }}
      </button>
    </div>
  </div>
</template>

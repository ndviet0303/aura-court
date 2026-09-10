<script setup lang="ts">
import { computed, ref } from 'vue'
import type { PartnerApplication, PartnerDocumentType } from '../types'

const props = defineProps<{
  application: PartnerApplication
  uploading: boolean
  error?: string | null
  fieldErrors?: Record<string, string[]>
}>()

const emit = defineEmits<{
  (e: 'upload', data: { file: File; documentType: string }): void
  (e: 'back'): void
  (e: 'next'): void
}>()

interface DocRequirement {
  type: PartnerDocumentType
  title: string
  description: string
  required: boolean
}

const documentRequirements = computed<DocRequirement[]>(() => {
  const type = props.application.business_type || 'individual'
  const list: DocRequirement[] = []

  if (type === 'enterprise' || type === 'household') {
    list.push({
      type: 'business_license',
      title: 'Giấy chứng nhận đăng ký kinh doanh / GPKD',
      description: 'Bản chụp hoặc scan rõ nét mặt trước giấy phép đăng ký kinh doanh.',
      required: true,
    })
  }

  if (type === 'enterprise') {
    list.push({
      type: 'tax_certificate',
      title: 'Thông báo mã số thuế / Giấy chứng nhận đăng ký thuế',
      description: 'Giấy chứng nhận đăng ký thuế hoặc tờ khai thuế gần nhất.',
      required: false,
    })
  }

  list.push(
    {
      type: 'identity_front',
      title: 'CCCD / Hộ chiếu người đại diện (Mặt trước)',
      description: 'Ảnh chụp mặt trước rõ thông tin, không bị lóa hoặc mất góc.',
      required: true,
    },
    {
      type: 'identity_back',
      title: 'CCCD / Hộ chiếu người đại diện (Mặt sau)',
      description: 'Ảnh chụp mặt sau rõ nét vân tay và thông tin cơ quan cấp.',
      required: true,
    },
    {
      type: 'premises_proof',
      title: 'Giấy tờ quyền sử dụng mặt bằng sân thể thao',
      description: 'Hợp đồng thuê mặt bằng, quyền sử dụng đất hoặc giấy phép xây dựng.',
      required: true,
    }
  )

  return list
})

function getUploadedDoc(type: string) {
  return props.application.documents?.find((d) => d.document_type === type)
}

function handleFileChange(event: Event, docType: string) {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (file) {
    emit('upload', { file, documentType: docType })
    target.value = ''
  }
}

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}
</script>

<template>
  <div class="document-upload-step space-y-6">
    <div>
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">
        2. Tải lên Hồ sơ Xác thực (Private Documents)
      </h3>
      <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
        Các tài liệu được lưu trữ trên vùng nhớ riêng tư an toàn, chỉ phục vụ mục đích kiểm duyệt định danh.
      </p>
    </div>

    <!-- Documents List -->
    <div class="space-y-4">
      <div
        v-for="req in documentRequirements"
        :key="req.type"
        class="border rounded-lg p-4 bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700"
      >
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <span class="text-sm font-semibold text-gray-900 dark:text-white">
                {{ req.title }}
              </span>
              <span v-if="req.required" class="text-xs text-red-500 font-medium">* Bắt buộc</span>
              <span v-else class="text-xs text-gray-400">Không bắt buộc</span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
              {{ req.description }}
            </p>
          </div>

          <!-- Upload Status / Action -->
          <div class="flex items-center gap-3">
            <div v-if="getUploadedDoc(req.type)" class="text-right">
              <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                <span class="material-symbols-outlined text-sm">check_circle</span>
                Đã tải lên
              </span>
              <p class="text-[11px] text-gray-500 dark:text-gray-400 max-w-[150px] truncate">
                {{ getUploadedDoc(req.type)?.original_name }} ({{ formatSize(getUploadedDoc(req.type)?.file_size_bytes || 0) }})
              </p>
            </div>

            <label
              class="px-3 py-1.5 text-xs font-medium rounded-lg border cursor-pointer inline-flex items-center gap-1.5 transition-colors"
              :class="uploading ? 'opacity-50 pointer-events-none' : 'hover:bg-gray-50 dark:hover:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200'"
            >
              <span class="material-symbols-outlined text-sm">upload_file</span>
              {{ getUploadedDoc(req.type) ? 'Tải lại' : 'Tải lên' }}
              <input
                type="file"
                class="hidden"
                accept=".pdf,.png,.jpg,.jpeg"
                @change="handleFileChange($event, req.type)"
              />
            </label>
          </div>
        </div>

        <p v-if="fieldErrors?.[req.type]?.length" class="text-xs text-red-600 dark:text-red-400 mt-2">
          {{ fieldErrors?.[req.type]?.[0] }}
        </p>
      </div>
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
        :disabled="uploading"
        class="px-6 py-2 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg disabled:opacity-50"
        @click="emit('next')"
      >
        Tiếp tục: Thỏa thuận & Gửi duyệt
      </button>
    </div>
  </div>
</template>

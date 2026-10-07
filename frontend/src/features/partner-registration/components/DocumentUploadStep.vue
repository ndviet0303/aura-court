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

  if (type === 'company') {
    list.push({
      type: 'business_license',
      title: 'Giấy phép kinh doanh',
      description: 'Bản chụp hoặc scan giấy phép đăng ký doanh nghiệp.',
      required: true,
    })
  }

  if (type === 'household') {
    list.push({
      type: 'business_registration_certificate',
      title: 'Giấy chứng nhận đăng ký hộ kinh doanh',
      description: 'Bản chụp hoặc scan giấy đăng ký hộ kinh doanh.',
      required: true,
    })
  }

  list.push(
    {
      type: 'representative_id_front',
      title: 'CCCD / Hộ chiếu người đại diện (Mặt trước)',
      description: 'Ảnh chụp mặt trước rõ thông tin, không bị lóa hoặc mất góc.',
      required: true,
    },
    {
      type: 'representative_id_back',
      title: 'CCCD / Hộ chiếu người đại diện (Mặt sau)',
      description: 'Ảnh chụp mặt sau rõ nét vân tay và thông tin cơ quan cấp.',
      required: true,
    },
    {
      type: 'bank_account_confirmation',
      title: 'Xác nhận tài khoản ngân hàng',
      description: 'Tài liệu xác minh chủ tài khoản và số tài khoản đã khai báo.',
      required: true,
    },
    {
      type: 'venue_operation_rights',
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
  <div class="document-upload-step">
    <div class="step-head">
      <h3 class="step-title">
        2. Tải lên Hồ sơ Xác thực (Private Documents)
      </h3>
      <p class="step-sub">
        Các tài liệu được lưu trữ trên vùng nhớ riêng tư an toàn, chỉ phục vụ mục đích kiểm duyệt định danh của đội ngũ Aura Sports.
      </p>
    </div>

    <!-- Documents List -->
    <div class="documents-list">
      <div
        v-for="req in documentRequirements"
        :key="req.type"
        class="doc-card"
      >
        <div class="doc-main-row">
          <div class="doc-info-col">
            <div class="doc-title-row">
              <span class="doc-title">{{ req.title }}</span>
              <span v-if="req.required" class="req-badge">* Bắt buộc</span>
              <span v-else class="opt-badge">Không bắt buộc</span>
            </div>
            <p class="doc-desc">{{ req.description }}</p>
          </div>

          <!-- Upload Status / Action -->
          <div class="doc-actions-col">
            <div v-if="getUploadedDoc(req.type)" class="uploaded-box">
              <span class="uploaded-pill">
                <span class="material-symbols-outlined icon-16">check_circle</span>
                <span>Đã tải lên</span>
              </span>
              <p class="uploaded-file-meta">
                {{ getUploadedDoc(req.type)?.file_name }} ({{ formatSize(getUploadedDoc(req.type)?.file_size || 0) }})
              </p>
            </div>

            <label
              class="btn-upload-file"
              :class="{ 'is-uploading': uploading }"
            >
              <span class="material-symbols-outlined icon-16">upload_file</span>
              <span>{{ getUploadedDoc(req.type) ? 'Tải lại' : 'Tải lên' }}</span>
              <input
                type="file"
                class="hidden-file-input"
                accept=".pdf,.png,.jpg,.jpeg"
                @change="handleFileChange($event, req.type)"
              />
            </label>
          </div>
        </div>

        <p v-if="fieldErrors?.[req.type]?.length" class="field-error-msg">
          {{ fieldErrors?.[req.type]?.[0] }}
        </p>
      </div>
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
        :disabled="uploading"
        class="btn-next-step"
        @click="emit('next')"
      >
        <span>Tiếp tục: Thỏa thuận & Gửi duyệt</span>
        <span class="material-symbols-outlined icon-18">arrow_forward</span>
      </button>
    </div>
  </div>
</template>

<style src="../styles/partner-registration.css"></style>

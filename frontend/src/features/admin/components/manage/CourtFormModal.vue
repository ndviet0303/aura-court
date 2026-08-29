<script setup lang="ts">
import { ref, computed } from 'vue'
import type { CourtForm } from '../../composables/useAdminManageCourts'
import type { Venue, SportZone } from '@/features/court/types/court'
import { adminApi } from '../../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'

const props = defineProps<{
  show: boolean
  editingCourtId: number | null
  courtForm: CourtForm
  venues: Venue[]
  sportZones: SportZone[]
  isDemoAdmin?: boolean
  saving?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save'): void
}>()

const uploadingImage = ref(false)
const uploadError = ref('')
const fileInput = ref<HTMLInputElement | null>(null)

const courtImagePresets = [
  {
    name: 'Yonex BWF',
    sub: 'Thảm chuẩn 5 lớp',
    url: 'https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?auto=format&fit=crop&w=800&q=80',
  },
  {
    name: 'Thảm Xanh',
    sub: 'Sân đôi phong trào',
    url: 'https://images.unsplash.com/photo-1541534741688-6078c6bfb5c5?auto=format&fit=crop&w=800&q=80',
  },
  {
    name: 'Indoor VIP',
    sub: 'Nhà thi đấu kín',
    url: 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=800&q=80',
  },
  {
    name: 'Đèn LED Pro',
    sub: 'Chiếu sáng 500 Lux',
    url: 'https://images.unsplash.com/photo-1613918108466-292b78a8ef95?auto=format&fit=crop&w=800&q=80',
  },
]

const courtDescriptionTags = [
  { label: 'Thảm BWF 5 lớp tiêu chuẩn' },
  { label: 'Đèn LED chống chói 500 Lux' },
  { label: 'Điều hòa không khí trung tâm' },
  { label: 'Ghế nghỉ trọng tài & VĐV' },
  { label: 'Bảng điểm điện tử LED' },
  { label: 'Thảm giảm chấn bảo vệ khớp gối' },
]

function toggleDescriptionTag(tagLabel: string) {
  if (!props.courtForm.description) {
    props.courtForm.description = tagLabel
    return
  }
  if (props.courtForm.description.includes(tagLabel)) {
    props.courtForm.description = props.courtForm.description
      .replace(new RegExp(`(^|,\\s*)` + tagLabel.replace(/[-/\\^$*+?.()|[\]{}]/g, '\\$&')), '')
      .replace(/^,\s*/, '')
      .trim()
  } else {
    props.courtForm.description = `${props.courtForm.description}, ${tagLabel}`
  }
}

async function handleFileUpload(event: Event) {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) return

  uploadError.value = ''
  uploadingImage.value = true

  if (props.isDemoAdmin) {
    setTimeout(() => {
      props.courtForm.image_url = URL.createObjectURL(file)
      uploadingImage.value = false
    }, 600)
    return
  }

  try {
    const formData = new FormData()
    formData.append('image', file)
    const res = await adminApi.uploadImage(formData)
    props.courtForm.image_url = res.url
  } catch (err: unknown) {
    uploadError.value = errorMessage(err, 'Không thể tải ảnh lên Cloudflare R2.')
  } finally {
    uploadingImage.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-lg">
      <div class="modal-header">
        <h3 class="modal-title">
          {{ editingCourtId ? '✏️ Chỉnh Sửa Sân Cầu Lông' : '➕ Thêm Sân Cầu Lông Mới' }}
        </h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="modal-form-grid">
          <div class="form-group">
            <label class="form-label">Tên sân cầu lông <span class="required">*</span>:</label>
            <input
              v-model="courtForm.name"
              type="text"
              class="form-input"
              placeholder="Vd: Sân 1 (Thảm Yonex Đạt Chuẩn)..."
            />
          </div>

          <div class="form-group">
            <label class="form-label">Cụm sân (Địa điểm) <span class="required">*</span>:</label>
            <select v-model="courtForm.venue_id" class="form-input">
              <option v-for="v in venues" :key="v.id" :value="v.id">
                {{ v.name }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Khu vực thể thao (Sport Zone):</label>
            <select v-model="courtForm.sport_zone_id" class="form-input">
              <option :value="null">Mặc định (Khu Cầu Lông)</option>
              <option v-for="z in sportZones.filter(sz => sz.venue_id === courtForm.venue_id)" :key="z.id" :value="z.id">
                {{ z.name }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Trạng thái sân:</label>
            <label class="toggle-check-label">
              <input v-model="courtForm.is_active" type="checkbox" />
              <span>Cho phép khách đặt sân (Đang hoạt động)</span>
            </label>
          </div>

          <div class="form-group full-width">
            <label class="form-label">Mô tả tiện ích sân:</label>
            <textarea
              v-model="courtForm.description"
              rows="2"
              class="form-textarea"
              placeholder="Tiện ích sân, loại thảm, ánh sáng..."
            ></textarea>

            <div class="quick-tags-row">
              <span class="tag-prompt">Gợi ý nhanh:</span>
              <button
                v-for="tag in courtDescriptionTags"
                :key="tag.label"
                type="button"
                class="tag-btn"
                :class="{ active: courtForm.description?.includes(tag.label) }"
                @click="toggleDescriptionTag(tag.label)"
              >
                + {{ tag.label }}
              </button>
            </div>
          </div>

          <!-- Image URL & Preset Selection -->
          <div class="form-group full-width">
            <label class="form-label">Hình ảnh sân:</label>
            <div class="img-input-row">
              <input
                v-model="courtForm.image_url"
                type="text"
                class="form-input"
                placeholder="Nhập URL hình ảnh hoặc tải ảnh lên..."
              />
              <input
                ref="fileInput"
                type="file"
                accept="image/*"
                class="hidden-file-input"
                @change="handleFileUpload"
              />
              <button
                type="button"
                class="btn-upload"
                :disabled="uploadingImage"
                @click="fileInput?.click()"
              >
                <span v-if="uploadingImage">Đang tải...</span>
                <span v-else>📁 Tải Ảnh Lên R2</span>
              </button>
            </div>

            <div class="presets-row">
              <span class="tag-prompt">Ảnh mẫu đẹp:</span>
              <button
                v-for="preset in courtImagePresets"
                :key="preset.name"
                type="button"
                class="preset-thumb-btn"
                @click="courtForm.image_url = preset.url"
              >
                <img :src="preset.url" :alt="preset.name" />
                <span>{{ preset.name }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Huỷ</button>
        <button
          class="btn-primary"
          :disabled="saving || !courtForm.name.trim()"
          @click="emit('save')"
        >
          <span v-if="saving">Đang lưu...</span>
          <span v-else>Lưu Thông Tin Sân</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

.full-width {
  grid-column: 1 / -1;
}

.toggle-check-label {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 8px;
  cursor: pointer;
}

.quick-tags-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  margin-top: 8px;
}

.tag-prompt {
  font-size: 11px;
  color: var(--muted);
}

.tag-btn {
  padding: 4px 8px;
  border-radius: 6px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 11px;
  cursor: pointer;
}

.tag-btn.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
}

.img-input-row {
  display: flex;
  gap: 8px;
}

.hidden-file-input {
  display: none;
}

.btn-upload {
  padding: 0 16px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

.presets-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 10px;
  overflow-x: auto;
}

.preset-thumb-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 4px 8px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  cursor: pointer;
  font-size: 11px;
  white-space: nowrap;
}

.preset-thumb-btn img {
  width: 24px;
  height: 24px;
  border-radius: 4px;
  object-fit: cover;
}

.btn-cancel {
  padding: 10px 18px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}

.btn-primary {
  padding: 10px 24px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
</style>

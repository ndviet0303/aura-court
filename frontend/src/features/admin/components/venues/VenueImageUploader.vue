<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ApiError } from '@/core/types/api'
import { errorMessage } from '@/core/utils/errorMessage'
import { adminApi } from '../../api/adminApi'

// D11: images come only from the upload endpoint (JPG/PNG up to 5 MB); a venue holds at most 10.
const MAX_IMAGES = 10
const MAX_BYTES = 5 * 1024 * 1024

const images = defineModel<string[]>({ required: true })
defineProps<{ error?: string }>()

const { t } = useI18n()
const fileInput = ref<HTMLInputElement | null>(null)
const uploading = ref(false)
const uploadError = ref('')

async function handleFiles(event: Event) {
  const files = Array.from((event.target as HTMLInputElement).files ?? [])
  uploadError.value = ''
  uploading.value = true
  try {
    for (const file of files) {
      if (images.value.length >= MAX_IMAGES) {
        uploadError.value = t('admin.venues.imagesMax', { max: MAX_IMAGES })
        break
      }
      // Client hint only; the server checks type and size again and its message wins.
      if (file.size > MAX_BYTES) {
        uploadError.value = t('admin.venues.imageTooLarge', { name: file.name })
        continue
      }
      const formData = new FormData()
      formData.append('image', file)
      const res = await adminApi.uploadImage(formData)
      images.value = [...images.value, res.url]
    }
  } catch (err: unknown) {
    uploadError.value =
      (err as ApiError).errors?.image?.[0] ?? errorMessage(err, t('admin.manage.uploadImageError'))
  } finally {
    uploading.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}

function remove(index: number) {
  images.value = images.value.filter((_, i) => i !== index)
}
</script>

<template>
  <div class="image-uploader">
    <div v-if="images.length" class="image-grid">
      <div v-for="(src, index) in images" :key="src" class="image-tile">
        <img :src="src" alt="" class="image-thumb" loading="lazy" />
        <button
          type="button"
          class="image-remove"
          :aria-label="t('admin.venues.imagesRemove')"
          @click="remove(index)"
        >
          ✕
        </button>
      </div>
    </div>
    <input
      ref="fileInput"
      type="file"
      accept="image/jpeg,image/png"
      multiple
      class="hidden-file-input"
      @change="handleFiles"
    />
    <button
      type="button"
      class="btn-upload"
      :disabled="uploading || images.length >= MAX_IMAGES"
      @click="fileInput?.click()"
    >
      <span class="material-symbols-outlined icon-16">cloud_upload</span>
      <span>{{
        uploading ? t('admin.manage.uploadingText') : t('admin.venues.imagesUploadBtn')
      }}</span>
    </button>
    <span class="field-hint">{{ t('admin.venues.imagesHint') }}</span>
    <p v-if="uploadError || error" class="field-error">{{ uploadError || error }}</p>
  </div>
</template>

<style scoped>
.image-uploader {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.image-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(84px, 1fr));
  gap: 8px;
}

.image-tile {
  position: relative;
  aspect-ratio: 4 / 3;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid var(--outline);
}

.image-thumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.image-remove {
  position: absolute;
  top: 4px;
  right: 4px;
  width: 22px;
  height: 22px;
  border: none;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.6);
  color: #fff;
  font-size: 11px;
  cursor: pointer;
}

.hidden-file-input {
  display: none;
}

.btn-upload {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  align-self: flex-start;
  padding: 8px 14px;
  border-radius: 10px;
  border: 1px dashed var(--outline);
  background: transparent;
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.btn-upload:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.field-hint {
  font-size: 11px;
  color: var(--muted);
}

.field-error {
  margin: 0;
  font-size: 12px;
  color: #ef4444;
}

.icon-16 {
  font-size: 16px;
}
</style>

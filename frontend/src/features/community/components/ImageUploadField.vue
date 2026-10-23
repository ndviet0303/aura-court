<script setup lang="ts">
import { watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useImageUploads } from '../composables/useImageUploads'

const props = defineProps<{ max: number }>()
const images = defineModel<string[]>({ required: true })
// The parent keeps the submit button off while an upload runs.
const busy = defineModel<boolean>('uploading', { default: false })

const { t } = useI18n()
const { uploading, error, add, remove } = useImageUploads(images, props.max)
watch(uploading, (value) => (busy.value = value))

function pick(event: Event) {
  const input = event.target as HTMLInputElement
  add(Array.from(input.files ?? []))
  input.value = ''
}
</script>

<template>
  <div class="upload-field">
    <div class="upload-previews">
      <figure v-for="(url, index) in images" :key="url" class="upload-thumb">
        <img :src="url" :alt="t('community.images.alt', { n: index + 1 })" />
        <button
          type="button"
          class="upload-remove"
          :aria-label="t('community.images.remove')"
          @click="remove(index)"
        >
          <span class="material-symbols-outlined">close</span>
        </button>
      </figure>
      <label v-if="images.length < max" class="upload-add" :class="{ disabled: uploading }">
        <span class="material-symbols-outlined">{{
          uploading ? 'progress_activity' : 'add_photo_alternate'
        }}</span>
        <span>{{ t('community.images.add', { count: images.length, max }) }}</span>
        <input
          type="file"
          accept="image/jpeg,image/png,image/webp"
          multiple
          class="sr-only"
          :disabled="uploading"
          @change="pick"
        />
      </label>
    </div>
    <p class="form-hint">{{ t('community.images.hint') }}</p>
    <p v-if="error" class="form-error" role="alert">{{ error }}</p>
  </div>
</template>

<style scoped>
.upload-previews {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.upload-thumb {
  position: relative;
  width: 76px;
  height: 76px;
  margin: 0;
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid var(--outline);
}

.upload-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.upload-remove {
  position: absolute;
  top: 2px;
  right: 2px;
  display: flex;
  padding: 2px;
  border: 0;
  border-radius: 999px;
  background: rgba(0, 0, 0, 0.6);
  color: #fff;
  cursor: pointer;
}

.upload-remove .material-symbols-outlined {
  font-size: 16px;
}

.upload-add {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  width: 76px;
  height: 76px;
  border: 1px dashed var(--outline);
  border-radius: 10px;
  color: var(--muted);
  font-size: 11px;
  text-align: center;
  cursor: pointer;
}

.upload-add.disabled {
  cursor: progress;
  opacity: 0.6;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
}
</style>

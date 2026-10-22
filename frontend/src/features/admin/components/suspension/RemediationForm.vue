<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'

// Mirrors the server limits (BR-44): content 10–5000 after trim, 1–10 PDF/JPG/JPEG/PNG files of at most 10 MB.
const MIN_CONTENT = 10
const MAX_CONTENT = 5000
const MAX_FILES = 10
const MAX_BYTES = 10 * 1024 * 1024
const ALLOWED = /\.(pdf|jpe?g|png)$/i

const props = defineProps<{
  busy?: boolean
  error?: string
  fieldErrors?: Record<string, string[]>
}>()

const emit = defineEmits<{ (e: 'submit', content: string, files: File[]): void }>()

const { t } = useI18n()

const content = ref('')
const files = ref<File[]>([])
const fileInput = ref<HTMLInputElement | null>(null)

const contentLength = computed(() => content.value.trim().length)
const contentError = computed(() =>
  contentLength.value > 0 &&
  (contentLength.value < MIN_CONTENT || contentLength.value > MAX_CONTENT)
    ? t('admin.remediation.form.contentLength')
    : '',
)
const fileErrors = computed(() =>
  files.value.flatMap((file) =>
    !ALLOWED.test(file.name)
      ? [t('admin.remediation.form.fileType', { name: file.name })]
      : file.size > MAX_BYTES
        ? [t('admin.remediation.form.fileSize', { name: file.name })]
        : [],
  ),
)
const countError = computed(() =>
  files.value.length > MAX_FILES ? t('admin.remediation.form.fileCount') : '',
)
const canSubmit = computed(
  () =>
    !props.busy &&
    contentLength.value >= MIN_CONTENT &&
    contentLength.value <= MAX_CONTENT &&
    files.value.length >= 1 &&
    !countError.value &&
    !fileErrors.value.length,
)

// Server 422 keys are `content`, `files` and `files.N`.
const serverErrors = (prefix: string) =>
  Object.entries(props.fieldErrors ?? {})
    .filter(([key]) => key === prefix || key.startsWith(`${prefix}.`))
    .flatMap(([, messages]) => messages)

function addFiles(event: Event) {
  const input = event.target as HTMLInputElement
  files.value = [...files.value, ...Array.from(input.files ?? [])]
  input.value = ''
}

function removeFile(index: number) {
  files.value = files.value.filter((_, position) => position !== index)
}

function submit() {
  if (canSubmit.value) emit('submit', content.value.trim(), files.value)
}
</script>

<template>
  <section class="admin-detail-card">
    <h2 class="admin-detail-section-title">{{ t('admin.remediation.form.title') }}</h2>
    <p class="admin-detail-muted remediation-form-hint">{{ t('admin.remediation.form.hint') }}</p>
    <p v-if="error" class="admin-detail-error" role="alert">{{ error }}</p>

    <label class="form-group">
      <span class="form-label">{{ t('admin.remediation.form.content') }} *</span>
      <textarea v-model="content" rows="6" :maxlength="MAX_CONTENT" class="form-textarea" />
      <span class="form-hint">
        {{ t('admin.remediation.form.contentHint', { count: contentLength }) }}
      </span>
      <span v-if="contentError" class="form-error">{{ contentError }}</span>
      <span v-for="message in serverErrors('content')" :key="message" class="form-error">
        {{ message }}
      </span>
    </label>

    <div class="form-group">
      <span class="form-label">{{ t('admin.remediation.form.files') }} *</span>
      <input
        ref="fileInput"
        type="file"
        multiple
        accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
        class="remediation-file-input"
        @change="addFiles"
      />
      <AuraButton size="sm" variant="secondary" @click="fileInput?.click()">
        {{ t('admin.remediation.form.addFiles') }}
      </AuraButton>
      <span class="form-hint">
        {{ t('admin.remediation.form.filesHint', { count: files.length }) }}
      </span>
      <ul v-if="files.length" class="remediation-picked">
        <li v-for="(file, index) in files" :key="`${file.name}-${index}`">
          <span>{{ file.name }}</span>
          <button type="button" class="remediation-remove" @click="removeFile(index)">
            {{ t('admin.remediation.form.removeFile') }}
          </button>
        </li>
      </ul>
      <span v-if="countError" class="form-error">{{ countError }}</span>
      <span v-for="message in fileErrors" :key="message" class="form-error">{{ message }}</span>
      <span v-for="message in serverErrors('files')" :key="message" class="form-error">
        {{ message }}
      </span>
    </div>

    <AuraButton variant="primary" :loading="busy" :disabled="!canSubmit" @click="submit">
      {{ t('admin.remediation.form.submit') }}
    </AuraButton>
  </section>
</template>

<style scoped>
.remediation-form-hint {
  margin-bottom: 12px;
}

.remediation-file-input {
  display: none;
}

.remediation-picked {
  margin: 0;
  padding-left: 18px;
  font-size: 13px;
}

.remediation-picked li {
  display: flex;
  gap: 10px;
  align-items: center;
}

.remediation-remove {
  border: none;
  background: none;
  color: #dc2626;
  font-size: 12px;
  cursor: pointer;
}
</style>

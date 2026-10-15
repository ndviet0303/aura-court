<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import type { CourtForm } from '../../composables/useAdminManageCourts'
import type { SportZone } from '@/features/court/types/court'
import { adminApi } from '../../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'
import SelectDropdown from '@/shared/components/forms/SelectDropdown.vue'
import CourtImagePresets from './CourtImagePresets.vue'

const props = defineProps<{
  show: boolean
  editingCourtId: number | null
  // Picked by the caller; only used to list that venue's sport zones.
  venueId: number
  sportZones: SportZone[]
  saving?: boolean
}>()

const name = defineModel<string>('name', { required: true })
const address = defineModel<string>('address', { required: true })
const sportZoneId = defineModel<number | null>('sportZoneId', { required: true })
const surfaceType = defineModel<CourtForm['surface_type']>('surfaceType', { required: true })
// A cleared number input yields '', which is not a valid capacity.
const capacity = defineModel<number | null>('capacity', {
  required: true,
  set: (value) => (typeof value === 'number' ? value : null),
})
const isActive = defineModel<boolean>('isActive', { required: true })
const description = defineModel<string>('description', { required: true })
const imageUrl = defineModel<string>('imageUrl', { required: true })

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save'): void
}>()

const { t } = useI18n()

const uploadingImage = ref(false)
const uploadError = ref('')
const fileInput = ref<HTMLInputElement | null>(null)

const sportZoneOptions = computed(() => [
  { value: '', label: t('admin.manage.defaultSportZone') },
  ...props.sportZones
    .filter((z) => z.venue_id === props.venueId)
    .map((z) => ({ value: z.id, label: resolveLocalizedText(z.name) })),
])

const surfaceOptions = computed(() => [
  { value: '', label: t('admin.manage.unspecified') },
  { value: 'wood', label: t('admin.manage.surfaceWood') },
  { value: 'synthetic', label: t('admin.manage.surfaceSynthetic') },
  { value: 'acrylic', label: t('admin.manage.surfaceAcrylic') },
  { value: 'concrete', label: t('admin.manage.surfaceConcrete') },
  { value: 'grass', label: t('admin.manage.surfaceGrass') },
  { value: 'clay', label: t('admin.manage.surfaceClay') },
  { value: 'hard', label: t('admin.manage.surfaceHard') },
  { value: 'other', label: t('admin.manage.surfaceOther') },
])

const courtDescriptionTags = computed(() => [
  { label: t('admin.manage.tagBwfMat') },
  { label: t('admin.manage.tagLedLight') },
  { label: t('admin.manage.tagCentralAc') },
  { label: t('admin.manage.tagSeating') },
  { label: t('admin.manage.tagLedScoreboard') },
  { label: t('admin.manage.tagShockAbsorption') },
])

function toggleDescriptionTag(tagLabel: string) {
  if (!description.value) {
    description.value = tagLabel
    return
  }
  if (description.value.includes(tagLabel)) {
    description.value = description.value
      .replace(new RegExp(`(^|,\\s*)` + tagLabel.replace(/[-/\\^$*+?.()|[\]{}]/g, '\\$&')), '')
      .replace(/^,\s*/, '')
      .trim()
  } else {
    description.value = `${description.value}, ${tagLabel}`
  }
}

async function handleFileUpload(event: Event) {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) return

  uploadError.value = ''
  uploadingImage.value = true

  try {
    const formData = new FormData()
    formData.append('image', file)
    const res = await adminApi.uploadImage(formData)
    imageUrl.value = res.url
  } catch (err: unknown) {
    uploadError.value = errorMessage(err, t('admin.manage.uploadImageError'))
  } finally {
    uploadingImage.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}

function handleKeyDown(e: KeyboardEvent) {
  if (e.key === 'Escape' && props.show) {
    emit('close')
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeyDown)
})
</script>

<template>
  <div
    v-if="show"
    class="modal-backdrop"
    role="dialog"
    aria-modal="true"
    @click.self="emit('close')"
  >
    <div class="modal-card modal-lg">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">{{
            editingCourtId ? 'edit' : 'add_circle'
          }}</span>
          <h3 class="modal-title">
            {{
              editingCourtId
                ? t('admin.manage.editCourtModalTitle')
                : t('admin.manage.addCourtModalTitle')
            }}
          </h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div v-if="uploadError" class="alert-banner-error">{{ uploadError }}</div>

        <div class="modal-form-grid">
          <div class="form-group">
            <label class="form-label"
              >{{ t('admin.manage.courtNameLabel') }} <span class="required">*</span></label
            >
            <input
              v-model="name"
              type="text"
              class="form-input"
              :placeholder="t('admin.manage.courtNamePlaceholder')"
            />
          </div>

          <div class="form-group">
            <label class="form-label" for="court-address">{{
              t('admin.manage.courtAddressLabel')
            }}</label>
            <input
              id="court-address"
              v-model="address"
              type="text"
              class="form-input"
              autocomplete="street-address"
              :placeholder="t('admin.manage.courtAddressPlaceholder')"
            />
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.manage.sportZoneLabel') }}</label>
            <!-- The dropdown only carries strings/numbers, so '' stands for "no zone" (null). -->
            <SelectDropdown
              :model-value="sportZoneId ?? ''"
              :options="sportZoneOptions"
              variant="bordered"
              :aria-label="t('admin.manage.sportZoneLabel')"
              @update:model-value="(value) => (sportZoneId = value === '' ? null : Number(value))"
            />
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.manage.courtSurfaceLabel') }}</label>
            <SelectDropdown
              :model-value="surfaceType"
              :options="surfaceOptions"
              variant="bordered"
              :aria-label="t('admin.manage.courtSurfaceLabel')"
              @update:model-value="(value) => (surfaceType = value as CourtForm['surface_type'])"
            />
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.manage.courtCapacityLabel') }}</label>
            <input v-model.number="capacity" type="number" class="form-input" min="1" max="100" />
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.manage.courtStatusLabel') }}</label>
            <label class="toggle-check-label">
              <input v-model="isActive" type="checkbox" />
              <span>{{ t('admin.manage.allowBookingCheckbox') }}</span>
            </label>
          </div>

          <div class="form-group full-width">
            <label class="form-label">{{ t('admin.manage.courtDescLabel') }}</label>
            <textarea
              v-model="description"
              rows="2"
              class="form-textarea"
              :placeholder="t('admin.manage.courtDescPlaceholder')"
            ></textarea>

            <div class="quick-tags-row">
              <span class="tag-prompt">{{ t('admin.manage.quickSuggestions') }}</span>
              <button
                v-for="tag in courtDescriptionTags"
                :key="tag.label"
                type="button"
                class="tag-btn"
                :class="{ active: description.includes(tag.label) }"
                @click="toggleDescriptionTag(tag.label)"
              >
                + {{ tag.label }}
              </button>
            </div>
          </div>

          <div class="form-group full-width">
            <label class="form-label">{{ t('admin.manage.courtImageLabel') }}</label>
            <div class="img-input-row">
              <input
                v-model="imageUrl"
                type="text"
                class="form-input"
                :placeholder="t('admin.manage.courtImagePlaceholder')"
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
                <span v-if="uploadingImage">{{ t('admin.manage.uploadingText') }}</span>
                <span v-else class="btn-upload-content">
                  <span class="material-symbols-outlined icon-16">cloud_upload</span>
                  <span>{{ t('admin.manage.uploadToR2Btn') }}</span>
                </span>
              </button>
            </div>

            <CourtImagePresets v-model="imageUrl" />
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" @click="emit('close')">
          {{ t('common.cancel') }}
        </button>
        <button
          type="button"
          class="btn-primary"
          :disabled="saving || !name.trim()"
          @click="emit('save')"
        >
          <span v-if="saving">{{ t('admin.manage.savingText') }}</span>
          <span v-else>{{ t('admin.manage.saveCourtInfoBtn') }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-title-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
}

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

.btn-upload-content {
  display: inline-flex;
  align-items: center;
  gap: 4px;
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

.alert-banner-error {
  padding: 8px 12px;
  border-radius: 8px;
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
  font-size: 13px;
  margin-bottom: 12px;
}

.icon-16 {
  font-size: 16px;
}
</style>

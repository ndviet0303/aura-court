<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
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
  saving?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save'): void
}>()

const { t } = useI18n()

const uploadingImage = ref(false)
const uploadError = ref('')
const fileInput = ref<HTMLInputElement | null>(null)

const courtImagePresets = computed(() => [
  {
    name: t('admin.manage.presetFootball'),
    sub: t('admin.manage.presetFootballSub'),
    url: '/courts/football-mini-turf.jpg',
  },
  {
    name: t('admin.manage.presetPickleball'),
    sub: t('admin.manage.presetPickleballSub'),
    url: '/courts/pickleball-neon.jpg',
  },
  {
    name: t('admin.manage.presetTennis'),
    sub: t('admin.manage.presetTennisSub'),
    url: '/courts/tennis-1.jpg',
  },
  {
    name: t('admin.manage.presetBasketball'),
    sub: t('admin.manage.presetBasketballSub'),
    url: '/courts/basketball-nba.jpg',
  },
  {
    name: t('admin.manage.presetYonex'),
    sub: t('admin.manage.presetYonexSub'),
    url: '/courts/badminton-0.jpg',
  },
  {
    name: t('admin.manage.presetBlue'),
    sub: t('admin.manage.presetBlueSub'),
    url: '/courts/badminton-court.jpg',
  },
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

  try {
    const formData = new FormData()
    formData.append('image', file)
    const res = await adminApi.uploadImage(formData)
    props.courtForm.image_url = res.url
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
              >{{ t('admin.manage.courtNameLabel') }} <span class="required">*</span>:</label
            >
            <input
              v-model="courtForm.name"
              type="text"
              class="form-input"
              :placeholder="t('admin.manage.courtNamePlaceholder')"
            />
          </div>

          <div class="form-group">
            <label class="form-label"
              >{{ t('admin.manage.venueLabel') }} <span class="required">*</span>:</label
            >
            <select v-model="courtForm.venue_id" class="form-input">
              <option v-for="v in venues" :key="v.id" :value="v.id">
                {{ resolveLocalizedText(v.name) }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.manage.sportZoneLabel') }}</label>
            <select v-model="courtForm.sport_zone_id" class="form-input">
              <option :value="null">{{ t('admin.manage.defaultSportZone') }}</option>
              <option
                v-for="z in sportZones.filter((sz) => sz.venue_id === courtForm.venue_id)"
                :key="z.id"
                :value="z.id"
              >
                {{ resolveLocalizedText(z.name) }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.manage.courtSurfaceLabel') }}</label>
            <select v-model="courtForm.surface_type" class="form-input">
              <option value="">{{ t('admin.manage.unspecified') }}</option>
              <option value="wood">{{ t('admin.manage.surfaceWood') }}</option>
              <option value="synthetic">{{ t('admin.manage.surfaceSynthetic') }}</option>
              <option value="acrylic">{{ t('admin.manage.surfaceAcrylic') }}</option>
              <option value="concrete">{{ t('admin.manage.surfaceConcrete') }}</option>
              <option value="grass">{{ t('admin.manage.surfaceGrass') }}</option>
              <option value="clay">{{ t('admin.manage.surfaceClay') }}</option>
              <option value="hard">{{ t('admin.manage.surfaceHard') }}</option>
              <option value="other">{{ t('admin.manage.surfaceOther') }}</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.manage.courtCapacityLabel') }}</label>
            <input
              v-model.number="courtForm.capacity"
              type="number"
              class="form-input"
              min="1"
              max="100"
            />
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.manage.courtStatusLabel') }}</label>
            <label class="toggle-check-label">
              <input v-model="courtForm.is_active" type="checkbox" />
              <span>{{ t('admin.manage.allowBookingCheckbox') }}</span>
            </label>
          </div>

          <div class="form-group full-width">
            <label class="form-label">{{ t('admin.manage.courtDescLabel') }}</label>
            <textarea
              v-model="courtForm.description"
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
                :class="{ active: courtForm.description?.includes(tag.label) }"
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
                v-model="courtForm.image_url"
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

            <div class="presets-row">
              <span class="tag-prompt">{{ t('admin.manage.presetImagesLabel') }}</span>
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
        <button type="button" class="btn-cancel" @click="emit('close')">
          {{ t('common.cancel') }}
        </button>
        <button
          type="button"
          class="btn-primary"
          :disabled="saving || !courtForm.name.trim()"
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

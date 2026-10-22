<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import type { CourtForm } from '../../composables/useAdminManageCourts'
import { SPORT_TYPES, type SportType } from '@/features/court/types/court'
import SelectDropdown from '@/shared/components/forms/SelectDropdown.vue'
import type { DropdownOption } from '@/shared/components'
import VenueImageUploader from '../venues/VenueImageUploader.vue'
import CourtPriceTablePanel from './CourtPriceTablePanel.vue'

// BR-27: the code is the court's name; venue and sport are fixed once the court exists.
const props = defineProps<{
  show: boolean
  editingCourtId: number | null
  saving?: boolean
  venueOptions: DropdownOption[]
  errors: string[]
}>()

const code = defineModel<string>('code', { required: true })
const venueId = defineModel<number>('venueId', { required: true })
const sportType = defineModel<SportType>('sportType', { required: true })
const surfaceType = defineModel<CourtForm['surface_type']>('surfaceType', { required: true })
// A cleared number input yields '', which is not a valid capacity.
const capacity = defineModel<number | null>('capacity', {
  required: true,
  set: (value) => (typeof value === 'number' ? value : null),
})
const description = defineModel<string>('description', { required: true })
// D11: uploaded images only, same rules as venue images.
const images = defineModel<string[]>('images', { required: true })

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save'): void
}>()

const { t } = useI18n()

const sportOptions = computed(() =>
  SPORT_TYPES.map((sport) => ({ value: sport, label: t(`sportTypes.${sport}`) })),
)

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
        <div v-for="message in errors" :key="message" class="alert-banner-error" role="alert">
          {{ message }}
        </div>

        <div class="modal-form-grid">
          <div class="form-group">
            <label class="form-label" for="court-code"
              >{{ t('admin.manage.courtNameLabel') }} <span class="required">*</span></label
            >
            <input
              id="court-code"
              v-model="code"
              type="text"
              class="form-input"
              maxlength="50"
              required
              :placeholder="t('admin.manage.courtNamePlaceholder')"
            />
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.manage.courtVenueLabel') }}</label>
            <SelectDropdown
              :model-value="venueId"
              :options="venueOptions"
              :disabled="!!editingCourtId"
              variant="bordered"
              :aria-label="t('admin.manage.courtVenueLabel')"
              @update:model-value="(value) => (venueId = Number(value))"
            />
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.manage.sportTypeLabel') }}</label>
            <SelectDropdown
              :model-value="sportType"
              :options="sportOptions"
              :disabled="!!editingCourtId"
              variant="bordered"
              :aria-label="t('admin.manage.sportTypeLabel')"
              @update:model-value="(value) => (sportType = value as SportType)"
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

          <div class="form-group full-width">
            <label class="form-label">{{ t('admin.manage.courtDescLabel') }}</label>
            <textarea
              v-model="description"
              rows="2"
              maxlength="2000"
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
            <VenueImageUploader v-model="images" />
          </div>

          <div class="form-group full-width">
            <CourtPriceTablePanel
              v-if="editingCourtId"
              :court-id="editingCourtId"
              :venue-id="venueId"
            />
            <p v-else class="form-hint">{{ t('admin.priceTables.court.saveFirstHint') }}</p>
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
          :disabled="saving || !code.trim() || !venueId"
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
</style>

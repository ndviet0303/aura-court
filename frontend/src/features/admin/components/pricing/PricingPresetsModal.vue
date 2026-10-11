<script setup lang="ts">
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { AuraButton, SelectDropdown } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import type { Venue, Court } from '@/features/court/types/court'

const props = defineProps<{
  show: boolean
  venues: Venue[]
  courts: Court[]
  applying?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (
    e: 'applyPreset',
    payload: { presetId: string; venueId?: number | null; courtId?: number | null },
  ): void
}>()

const { t } = useI18n()

const selectedVenueId = ref<number | ''>('')
const selectedCourtId = ref<number | ''>('')

const venueOptions = computed<DropdownOption[]>(() => [
  { value: '', label: t('admin.pricing.scopeAllVenues') },
  ...props.venues.map((v) => ({
    value: v.id,
    label: resolveLocalizedText(v.name),
    icon: 'location_on',
  })),
])

const availableCourts = computed(() => {
  if (selectedVenueId.value) {
    return props.courts.filter((c) => (c.venue_id || c.venue?.id) === Number(selectedVenueId.value))
  }
  return props.courts
})

const courtOptions = computed<DropdownOption[]>(() => [
  { value: '', label: t('admin.pricing.allCourtsInScope') },
  ...availableCourts.value.map((c) => ({
    value: c.id,
    label: resolveLocalizedText(c.name),
    icon: 'sports_tennis',
  })),
])

const presetTemplates = [
  {
    id: 'urban_peak',
    titleKey: 'admin.pricing.presetUrbanTitle',
    descKey: 'admin.pricing.presetUrbanDesc',
    badgeKey: 'admin.pricing.presetUrbanBadge',
    icon: 'trending_up',
    highlightRules: [
      'Peak: 17:00 - 21:00 (+50%)',
      'Morning: 06:00 - 11:00 (Base rate)',
      'Weekend Surcharge: T7 & CN (+25%)',
    ],
  },
  {
    id: 'student_discount',
    titleKey: 'admin.pricing.presetStudentTitle',
    descKey: 'admin.pricing.presetStudentDesc',
    badgeKey: 'admin.pricing.presetStudentBadge',
    icon: 'school',
    highlightRules: [
      'Lunch Promo: 11:00 - 14:00 (-25% discount)',
      'Applicable all weekdays Mon - Fri',
    ],
  },
  {
    id: 'holiday_surcharge',
    titleKey: 'admin.pricing.presetHolidayTitle',
    descKey: 'admin.pricing.presetHolidayDesc',
    badgeKey: 'admin.pricing.presetHolidayBadge',
    icon: 'celebration',
    highlightRules: ['Holiday Surcharge: +35% Full Day', 'High Priority (#5 override)'],
  },
  {
    id: 'night_owl',
    titleKey: 'admin.pricing.presetNightTitle',
    descKey: 'admin.pricing.presetNightDesc',
    badgeKey: 'admin.pricing.presetNightBadge',
    icon: 'bedtime',
    highlightRules: [
      'Late night: 21:00 - 23:00 (-15% discount)',
      'Optimizes empty late night slots',
    ],
  },
]

function handleVenueChange(val: string | number) {
  selectedVenueId.value = val ? Number(val) : ''
  selectedCourtId.value = ''
}

function handleApply(presetId: string) {
  emit('applyPreset', {
    presetId,
    venueId: selectedVenueId.value ? Number(selectedVenueId.value) : null,
    courtId: selectedCourtId.value ? Number(selectedCourtId.value) : null,
  })
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-lg">
      <!-- Modal Header -->
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">dashboard_customize</span>
          <h3 class="modal-title">{{ t('admin.pricing.presetsModalTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body">
        <!-- Target Scope Selector -->
        <div class="preset-target-bar">
          <span class="target-title">{{ t('admin.pricing.applyPresetTo') }}:</span>
          <div class="target-selects">
            <SelectDropdown
              :model-value="selectedVenueId"
              :options="venueOptions"
              variant="bordered"
              @update:model-value="handleVenueChange"
            />
            <SelectDropdown v-model="selectedCourtId" :options="courtOptions" variant="bordered" />
          </div>
        </div>

        <!-- Presets List -->
        <div class="presets-list">
          <div v-for="p in presetTemplates" :key="p.id" class="preset-card">
            <div class="p-left">
              <div class="p-icon-box">
                <span class="material-symbols-outlined p-icon">{{ p.icon }}</span>
              </div>
              <div class="p-info">
                <div class="p-title-row">
                  <strong class="p-title">{{ t(p.titleKey) }}</strong>
                  <span class="p-badge">{{ t(p.badgeKey) }}</span>
                </div>
                <p class="p-desc">{{ t(p.descKey) }}</p>

                <!-- Rules list tags -->
                <div class="p-rules-chips">
                  <span v-for="r in p.highlightRules" :key="r" class="rule-chip font-mono">
                    {{ r }}
                  </span>
                </div>
              </div>
            </div>

            <AuraButton
              variant="primary"
              :disabled="applying"
              :loading="applying"
              @click="handleApply(p.id)"
            >
              {{ t('admin.pricing.applyPresetBtn') }}
            </AuraButton>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <AuraButton variant="outline" @click="emit('close')">{{ t('common.close') }}</AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.modal-card {
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
  width: 100%;
  max-width: 680px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.25);
  overflow: hidden;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 24px;
  border-bottom: 1px solid var(--outline);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 10px;
}

.card-title-icon {
  font-size: 24px;
  color: var(--pink-dark, #db2777);
}

.modal-title {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
  color: var(--ink);
}

.modal-close-btn {
  background: transparent;
  border: none;
  font-size: 16px;
  color: var(--muted);
  cursor: pointer;
  padding: 4px;
}

.modal-body {
  padding: 20px 24px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.preset-target-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  padding: 14px 18px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.target-title {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
}

.target-selects {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  flex: 1;
  justify-content: flex-end;
}

.presets-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.preset-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  gap: 16px;
  transition:
    transform 0.15s ease,
    box-shadow 0.15s ease;
}

.preset-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.04);
}

.p-left {
  display: flex;
  gap: 16px;
  align-items: flex-start;
  flex: 1;
}

.p-icon-box {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: rgba(219, 39, 119, 0.08);
  color: var(--pink-dark, #db2777);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.p-icon {
  font-size: 22px;
}

.p-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.p-title-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.p-title {
  font-size: 14px;
  font-weight: 800;
  color: var(--ink);
}

.p-badge {
  font-size: 11px;
  font-weight: 700;
  color: #db2777;
  background: rgba(219, 39, 119, 0.1);
  padding: 2px 8px;
  border-radius: 6px;
}

.p-desc {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
  line-height: 1.4;
}

.p-rules-chips {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-top: 4px;
}

.rule-chip {
  font-size: 11px;
  padding: 2px 6px;
  border-radius: 4px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  color: var(--ink);
  border: 1px solid var(--outline);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  padding: 16px 24px;
  border-top: 1px solid var(--outline);
}
</style>

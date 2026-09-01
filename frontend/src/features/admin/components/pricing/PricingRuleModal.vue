<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { PriceRuleItem } from '../../composables/usePricingCalendarV2'

const props = defineProps<{
  show: boolean
  rule: PriceRuleItem | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save', payload: any): void
}>()

const { t } = useI18n()

const name = ref('')
const type = ref<'holiday' | 'peak' | 'special_event' | 'bad_weather' | 'surge'>('peak')
const priority = ref(2)
const adjustmentType = ref<'fixed' | 'percentage_add' | 'percentage_discount'>('fixed')
const adjustmentValue = ref(180000)
const startsAt = ref('17:00')
const endsAt = ref('21:00')

watch(
  () => props.rule,
  (r) => {
    if (r) {
      name.value = r.name
      type.value = r.type
      priority.value = r.priority
      adjustmentType.value = r.adjustment_type
      adjustmentValue.value = r.adjustment_value
      startsAt.value = r.starts_at || '17:00'
      endsAt.value = r.ends_at || '21:00'
    } else {
      name.value = ''
      type.value = 'peak'
      priority.value = 2
      adjustmentType.value = 'fixed'
      adjustmentValue.value = 180000
    }
  },
  { immediate: true }
)

function handleSave() {
  if (!name.value.trim()) return
  emit('save', {
    name: name.value.trim(),
    type: type.value,
    priority: priority.value,
    adjustment_type: adjustmentType.value,
    adjustment_value: adjustmentValue.value,
    starts_at: startsAt.value,
    ends_at: endsAt.value,
  })
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">{{ rule ? 'edit' : 'add_circle' }}</span>
          <h3 class="modal-title">
            {{ rule ? t('admin.pricing.editRuleTitle') : t('admin.pricing.createRuleTitle') }}
          </h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="rule-form-grid">
          <div class="form-group">
            <label class="form-label">{{ t('admin.pricing.ruleNameLabel') }}</label>
            <input v-model="name" type="text" class="form-input" :placeholder="t('admin.pricing.ruleNamePlaceholder')" />
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.pricing.ruleTypeLabel') }}</label>
            <select v-model="type" class="form-input">
              <option value="peak">{{ t('admin.pricing.typePeakOption') }}</option>
              <option value="holiday">{{ t('admin.pricing.typeHolidayOption') }}</option>
              <option value="surge">{{ t('admin.pricing.typeSurgeOption') }}</option>
              <option value="special_event">{{ t('admin.pricing.typeSpecialEventOption') }}</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.pricing.priorityLabel') }}</label>
            <input v-model.number="priority" type="number" min="1" max="10" class="form-input font-mono" />
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.pricing.adjustmentTypeLabel') }}</label>
            <select v-model="adjustmentType" class="form-input">
              <option value="fixed">{{ t('admin.pricing.adjFixedOption') }}</option>
              <option value="percentage_add">{{ t('admin.pricing.adjPercentAddOption') }}</option>
              <option value="percentage_discount">{{ t('admin.pricing.adjPercentDiscountOption') }}</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.pricing.adjustmentValueLabel') }}</label>
            <input v-model.number="adjustmentValue" type="number" class="form-input font-mono" />
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.pricing.applicableHoursLabel') }}</label>
            <div class="time-range-row">
              <input v-model="startsAt" type="time" class="form-input font-mono" />
              <span class="material-symbols-outlined icon-16">arrow_forward</span>
              <input v-model="endsAt" type="time" class="form-input font-mono" />
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" @click="emit('close')">{{ t('common.cancel') }}</button>
        <button type="button" class="btn-primary" :disabled="!name.trim()" @click="handleSave">
          {{ t('admin.pricing.saveRuleBtn') }}
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

.rule-form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.time-range-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-cancel {
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}

.btn-primary {
  padding: 8px 24px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}

.icon-16 {
  font-size: 16px;
}
</style>

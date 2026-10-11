<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { AuraButton, SelectDropdown } from '@/shared/components'
import type { MultiplierRule } from '../../types/membership'

const props = defineProps<{
  show: boolean
  rule: MultiplierRule | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save', payload: Partial<MultiplierRule>): void
}>()

const { t } = useI18n()

const formName = ref('')
const formMultiplier = ref(1.5)
const formTag = ref('Off-peak')
const formSchedule = ref('')
const formDesc = ref('')
const formIsActive = ref(true)

const tagOptions = computed(() => [
  { value: 'Off-peak', label: t('admin.membership.tagOffPeak') },
  { value: 'Birthday', label: t('admin.membership.tagBirthday') },
  { value: 'Tournament', label: t('admin.membership.tagTournament') },
  { value: 'Weekend', label: t('admin.membership.tagWeekend') },
  { value: 'Special', label: t('admin.membership.tagSpecial') },
])

watch(
  () => props.rule,
  (val) => {
    if (val) {
      formName.value = resolveLocalizedText(val.name)
      formMultiplier.value = val.multiplier
      formTag.value = val.tag
      formSchedule.value = resolveLocalizedText(val.schedule)
      formDesc.value = resolveLocalizedText(val.description)
      formIsActive.value = val.isActive
    } else {
      formName.value = ''
      formMultiplier.value = 1.5
      formTag.value = 'Off-peak'
      formSchedule.value = 'T2-T6, 08:00 - 15:00'
      formDesc.value = ''
      formIsActive.value = true
    }
  },
  { immediate: true },
)

function onSubmit() {
  if (!formName.value.trim()) return

  emit('save', {
    name: formName.value.trim(),
    multiplier: Number(formMultiplier.value),
    tag: formTag.value,
    schedule: formSchedule.value.trim(),
    description: formDesc.value.trim() || formSchedule.value.trim(),
    isActive: formIsActive.value,
  })
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-dialog-sm" role="dialog" aria-modal="true">
      <div class="modal-header">
        <div class="header-left">
          <div class="modal-icon-badge">
            <span class="material-symbols-outlined">tune</span>
          </div>
          <div>
            <h3 class="modal-title">
              {{
                rule ? t('admin.membership.editRuleTitle') : t('admin.membership.createRuleTitle')
              }}
            </h3>
            <p class="modal-subtitle">{{ t('admin.membership.ruleModalSub') }}</p>
          </div>
        </div>
        <button type="button" class="btn-close-circle" @click="emit('close')">
          <span class="material-symbols-outlined icon-18">close</span>
        </button>
      </div>

      <div class="modal-body custom-scroll">
        <form id="multiplier-rule-form" class="rule-form-stack" @submit.prevent="onSubmit">
          <div class="field-item">
            <label class="field-label required">{{ t('admin.membership.ruleNameLabel') }}</label>
            <input
              v-model="formName"
              type="text"
              class="field-input"
              :placeholder="t('admin.membership.ruleNamePlaceholder')"
              required
            />
          </div>

          <div class="grid-2">
            <div class="field-item">
              <label class="field-label required">{{
                t('admin.membership.ruleMultiplierLabel')
              }}</label>
              <input
                v-model.number="formMultiplier"
                type="number"
                step="0.1"
                min="1"
                max="10"
                class="field-input font-mono"
                required
              />
            </div>

            <div class="field-item">
              <label class="field-label">{{ t('admin.membership.ruleTagLabel') }}</label>
              <SelectDropdown v-model="formTag" :options="tagOptions" variant="bordered" />
            </div>
          </div>

          <div class="field-item">
            <label class="field-label required">{{
              t('admin.membership.ruleScheduleLabel')
            }}</label>
            <input
              v-model="formSchedule"
              type="text"
              class="field-input"
              :placeholder="t('admin.membership.ruleSchedulePlaceholder')"
              required
            />
          </div>

          <div class="field-item">
            <label class="field-label">{{ t('admin.membership.ruleDescLabel') }}</label>
            <textarea
              v-model="formDesc"
              rows="2"
              class="field-textarea"
              :placeholder="t('admin.membership.ruleDescPlaceholder')"
            ></textarea>
          </div>

          <div class="toggle-row">
            <label class="toggle-label"
              ><input v-model="formIsActive" type="checkbox" class="check-box" /><span>{{
                t('admin.membership.ruleActiveLabel')
              }}</span></label
            >
          </div>
        </form>
      </div>

      <div class="modal-footer">
        <AuraButton type="button" variant="secondary" @click="emit('close')">{{
          t('admin.membership.cancelBtn')
        }}</AuraButton>
        <AuraButton type="submit" variant="primary" form="multiplier-rule-form">
          <span class="material-symbols-outlined icon-18">check</span>
          <span>{{ t('admin.membership.saveRuleSubmitBtn') }}</span>
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(8px);
  z-index: 1200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.modal-dialog-sm {
  background: var(--surface);
  border-radius: 24px;
  width: 100%;
  max-width: 540px;
  max-height: 88vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.4);
  border: 1px solid var(--outline);
  overflow: hidden;
}
:global(html.dark) .modal-dialog-sm {
  background: #16181f;
  border-color: #2b2f3a;
}
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 24px;
  border-bottom: 1px solid var(--outline);
  background: rgba(219, 39, 119, 0.03);
  flex-shrink: 0;
}
:global(html.dark) .modal-header {
  border-bottom-color: #232732;
}
.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}
.modal-icon-badge {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  background: rgba(219, 39, 119, 0.12);
  color: var(--pink-dark, #db2777);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
}
.modal-title {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
  color: var(--ink);
}
.modal-subtitle {
  margin: 2px 0 0;
  font-size: 12px;
  color: var(--muted);
}
.btn-close-circle {
  width: 32px;
  height: 32px;
  border-radius: 9999px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}
.btn-close-circle:hover {
  color: var(--ink);
  border-color: var(--ink);
}
.modal-body {
  padding: 20px 24px;
  overflow-y: auto;
  flex: 1;
}
.rule-form-stack {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.field-item {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.field-label {
  font-size: 11.5px;
  font-weight: 700;
  color: var(--muted);
}
.field-input {
  height: 42px;
  box-sizing: border-box;
  padding: 0 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13.5px;
  outline: none;
}
.field-textarea {
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13.5px;
  outline: none;
}
:global(html.dark) .field-input,
:global(html.dark) .field-textarea {
  background: #14161c;
  border-color: #2d313d;
}
.field-input:focus,
.field-textarea:focus {
  border-color: var(--pink-dark, #db2777);
}
.font-mono {
  font-family: monospace;
}
.toggle-row {
  display: flex;
  align-items: center;
}
.toggle-label {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}
.check-box {
  accent-color: var(--pink-dark, #db2777);
  width: 16px;
  height: 16px;
  cursor: pointer;
}
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px;
  border-top: 1px solid var(--outline);
  background: var(--surface);
  flex-shrink: 0;
}
:global(html.dark) .modal-footer {
  background: #16181f;
  border-top-color: #232732;
}
.icon-18 {
  font-size: 18px;
}
@media (max-width: 600px) {
  .grid-2 {
    grid-template-columns: 1fr;
  }
}
</style>

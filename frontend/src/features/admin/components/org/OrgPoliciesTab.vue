<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import type { Organization } from '../../composables/useOrganizationOwner'

const props = defineProps<{
  org: Organization
  saving?: boolean
}>()

const emit = defineEmits<{
  (e: 'save', payload: Partial<Organization>): void
}>()

const { t } = useI18n()

const autoApprove = ref(props.org.auto_approve_booking ?? true)
const resaleFee = ref(props.org.resale_fee_percent ?? 5)
const graceHours = ref(props.org.cancellation_grace_hours ?? 12)

watch(
  () => props.org,
  (o) => {
    autoApprove.value = o.auto_approve_booking ?? true
    resaleFee.value = o.resale_fee_percent ?? 5
    graceHours.value = o.cancellation_grace_hours ?? 12
  },
  { deep: true }
)

function handleSave() {
  emit('save', {
    auto_approve_booking: autoApprove.value,
    resale_fee_percent: Number(resaleFee.value),
    cancellation_grace_hours: Number(graceHours.value),
  })
}
</script>

<template>
  <div class="org-policies-tab-card">
    <!-- Header -->
    <div class="card-header">
      <div class="title-with-icon">
        <span class="material-symbols-outlined card-title-icon">tune</span>
        <div>
          <h3 class="tab-title">{{ t('admin.org.policiesTitle') }}</h3>
          <p class="tab-sub">{{ t('admin.org.policiesSubtitle') }}</p>
        </div>
      </div>
    </div>

    <form class="policies-form" @submit.prevent="handleSave">
      <!-- Auto Approve Rule -->
      <div class="policy-card-item">
        <div class="p-info">
          <div class="p-header">
            <span class="material-symbols-outlined p-icon">bolt</span>
            <strong class="p-title">{{ t('admin.org.autoApproveTitle') }}</strong>
          </div>
          <p class="p-desc">{{ t('admin.org.autoApproveDesc') }}</p>
        </div>

        <label class="switch-toggle" aria-label="Auto approve toggle">
          <input v-model="autoApprove" type="checkbox" />
          <span class="slider"></span>
        </label>
      </div>

      <!-- Resale Fee Rule -->
      <div class="policy-card-item">
        <div class="p-info">
          <div class="p-header">
            <span class="material-symbols-outlined p-icon">percent</span>
            <strong class="p-title">{{ t('admin.org.resaleFeeLabel') }}</strong>
          </div>
          <p class="p-desc">{{ t('admin.org.resaleFeeDesc') }}</p>
        </div>

        <div class="p-control-box">
          <input
            v-model.number="resaleFee"
            type="range"
            min="0"
            max="20"
            step="1"
            class="range-slider"
          />
          <div class="number-input-wrap">
            <input
              v-model.number="resaleFee"
              type="number"
              min="0"
              max="20"
              class="form-input font-mono num-input"
            />
            <span class="unit-tag">%</span>
          </div>
        </div>
      </div>

      <!-- Grace Hours Rule -->
      <div class="policy-card-item">
        <div class="p-info">
          <div class="p-header">
            <span class="material-symbols-outlined p-icon">schedule</span>
            <strong class="p-title">{{ t('admin.org.graceHoursLabel') }}</strong>
          </div>
          <p class="p-desc">{{ t('admin.org.graceHoursDesc') }}</p>
        </div>

        <div class="p-control-box">
          <input
            v-model.number="graceHours"
            type="range"
            min="1"
            max="72"
            step="1"
            class="range-slider"
          />
          <div class="number-input-wrap">
            <input
              v-model.number="graceHours"
              type="number"
              min="1"
              max="72"
              class="form-input font-mono num-input"
            />
            <span class="unit-tag">hrs</span>
          </div>
        </div>
      </div>

      <!-- Form Actions -->
      <div class="form-actions">
        <AuraButton
          type="submit"
          variant="primary"
          :disabled="saving"
        >
          <span
            class="material-symbols-outlined icon-18"
            :class="{ 'spin-icon': saving }"
          >
            {{ saving ? 'sync' : 'save' }}
          </span>
          <span>{{ t('admin.org.savePoliciesBtn') }}</span>
        </AuraButton>
      </div>
    </form>
  </div>
</template>

<style scoped>
.org-policies-tab-card {
  display: flex;
  flex-direction: column;
  gap: 22px;
  padding: 28px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 12px;
}

.card-title-icon {
  font-size: 26px;
  color: var(--pink-dark, #db2777);
}

.tab-title {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
  color: var(--ink);
}

.tab-sub {
  margin: 2px 0 0 0;
  font-size: 12.5px;
  color: var(--muted);
}

.policies-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.policy-card-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  gap: 24px;
  flex-wrap: wrap;
  transition: all 0.2s ease;
}

.policy-card-item:hover {
  border-color: var(--pink-dark, #db2777);
  background: var(--surface);
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.03);
}

.p-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
  min-width: 260px;
}

.p-header {
  display: flex;
  align-items: center;
  gap: 8px;
}

.p-icon {
  font-size: 20px;
  color: var(--pink-dark, #db2777);
}

.p-title {
  font-size: 14.5px;
  font-weight: 700;
  color: var(--ink);
}

.p-desc {
  margin: 0;
  font-size: 12.5px;
  color: var(--muted);
  line-height: 1.5;
}

.p-control-box {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-shrink: 0;
}

.range-slider {
  width: 130px;
  accent-color: var(--pink-dark, #db2777);
  cursor: pointer;
}

.number-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.num-input {
  width: 84px;
  padding: 8px 30px 8px 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 14px;
  font-weight: 700;
  text-align: right;
}

.num-input:focus {
  outline: none;
  border-color: var(--pink-dark, #db2777);
  box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.15);
}

.unit-tag {
  position: absolute;
  right: 10px;
  font-size: 12px;
  color: var(--muted);
  font-weight: 700;
  pointer-events: none;
}

/* Switch toggle */
.switch-toggle {
  position: relative;
  display: inline-block;
  width: 48px;
  height: 26px;
  flex-shrink: 0;
}

.switch-toggle input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  inset: 0;
  background-color: var(--outline);
  transition: 0.25s;
  border-radius: 34px;
}

.slider:before {
  position: absolute;
  content: "";
  height: 20px;
  width: 20px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.25s;
  border-radius: 50%;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
}

input:checked + .slider {
  background-color: var(--pink-dark, #db2777);
}

input:checked + .slider:before {
  transform: translateX(22px);
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  border-top: 1px solid var(--outline);
  padding-top: 18px;
}

.icon-18 {
  font-size: 18px;
}

.spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  100% {
    transform: rotate(360deg);
  }
}

:global(html.dark) .org-policies-tab-card {
  background: var(--surface, #1e191c);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .policy-card-item {
  background: var(--surface-subtle, #262024);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .policy-card-item:hover {
  background: var(--soft, #30292e);
  border-color: var(--pink-dark, #E58E9F);
}

:global(html.dark) .p-title {
  color: var(--ink, #FAF7F3);
}

:global(html.dark) .p-desc {
  color: var(--muted, #a89fa4);
}

:global(html.dark) .num-input {
  background: var(--surface, #1e191c);
  color: var(--ink, #FAF7F3);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .unit-tag {
  color: var(--muted, #a89fa4);
}
</style>

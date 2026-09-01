<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import type { MembershipPlan, BillingCycle } from '../../types/membership'

const props = defineProps<{
  show: boolean
  plan: MembershipPlan | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save', payload: Partial<MembershipPlan>, createNewVersion: boolean): void
}>()

const { t } = useI18n()

const formName = ref('')
const formCode = ref('')
const formDesc = ref('')
const formPrice = ref(500000)
const formCycle = ref<BillingCycle>('monthly')
const formCourtDiscount = ref(10)
const formPointMultiplier = ref(1.5)
const formAdvanceDays = ref(7)
const formFreeHours = ref(0)
const formWaiveCancelFee = ref(false)
const formIsPopular = ref(false)
const formPerks = ref<string[]>([])
const newPerkInput = ref('')
const createNewVersion = ref(false)

watch(
  () => props.plan,
  (val) => {
    if (val) {
      formName.value = val.name
      formCode.value = val.code
      formDesc.value = val.description
      formPrice.value = val.price
      formCycle.value = val.billingCycle
      formCourtDiscount.value = val.courtDiscountPercent
      formPointMultiplier.value = val.pointMultiplier
      formAdvanceDays.value = val.advanceBookingDays
      formFreeHours.value = val.freeWeekendHours
      formWaiveCancelFee.value = val.cancellationFeeWaived
      formIsPopular.value = !!val.isPopular
      formPerks.value = [...(val.perks || [])]
      createNewVersion.value = true
    } else {
      formName.value = ''
      formCode.value = ''
      formDesc.value = ''
      formPrice.value = 600000
      formCycle.value = 'monthly'
      formCourtDiscount.value = 15
      formPointMultiplier.value = 1.5
      formAdvanceDays.value = 7
      formFreeHours.value = 0
      formWaiveCancelFee.value = false
      formIsPopular.value = false
      formPerks.value = ['Giảm 15% đặt sân giờ vàng', 'Tích lũy x1.5 điểm thưởng', 'Đặt sân trước 7 ngày']
      createNewVersion.value = false
    }
  },
  { immediate: true },
)

function addPerk() {
  if (newPerkInput.value.trim()) {
    formPerks.value.push(newPerkInput.value.trim())
    newPerkInput.value = ''
  }
}

function removePerk(idx: number) {
  formPerks.value.splice(idx, 1)
}

function onSubmit() {
  if (!formName.value.trim()) return

  emit(
    'save',
    {
      name: formName.value.trim(),
      code: (formCode.value || formName.value.slice(0, 4)).toUpperCase().trim(),
      description: formDesc.value.trim(),
      price: Number(formPrice.value),
      billingCycle: formCycle.value,
      courtDiscountPercent: Number(formCourtDiscount.value),
      pointMultiplier: Number(formPointMultiplier.value),
      advanceBookingDays: Number(formAdvanceDays.value),
      freeWeekendHours: Number(formFreeHours.value),
      cancellationFeeWaived: formWaiveCancelFee.value,
      isPopular: formIsPopular.value,
      perks: formPerks.value,
    },
    createNewVersion.value,
  )
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-box" role="dialog" aria-modal="true">
      <!-- Modal Header -->
      <div class="modal-header">
        <div class="header-title-block">
          <span class="material-symbols-outlined header-icon">
            {{ plan ? 'edit_note' : 'add_circle' }}
          </span>
          <div>
            <h3 class="modal-title">{{ plan ? t('admin.membership.editPlanTitle', { name: resolveLocalizedText(plan.name) }) : t('admin.membership.createPlanTitle') }}</h3>
            <p class="modal-subtitle">{{ t('admin.membership.planModalSub') }}</p>
          </div>
        </div>
        <button type="button" class="btn-close" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <!-- Modal Body -->
      <form class="modal-body" @submit.prevent="onSubmit">
        <div class="form-grid">
          <!-- Row 1: Name & Code -->
          <div class="form-group">
            <label class="form-label">{{ t('admin.membership.planNameLabel') }}</label>
            <input
              v-model="formName"
              type="text"
              class="form-input"
              :placeholder="t('admin.membership.planNamePlaceholder')"
              required
            />
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.membership.planCodeLabel') }}</label>
            <input
              v-model="formCode"
              type="text"
              class="form-input font-mono"
              :placeholder="t('admin.membership.planCodePlaceholder')"
            />
          </div>

          <!-- Row 2: Price & Cycle -->
          <div class="form-group">
            <label class="form-label">{{ t('admin.membership.priceLabel') }}</label>
            <input
              v-model.number="formPrice"
              type="number"
              step="10000"
              class="form-input font-mono"
              required
            />
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.membership.renewalCycleLabel') }}</label>
            <select v-model="formCycle" class="form-select">
              <option value="monthly">{{ t('admin.membership.cycleMonthly') }}</option>
              <option value="quarterly">{{ t('admin.membership.cycleQuarterly') }}</option>
              <option value="yearly">{{ t('admin.membership.cycleYearly') }}</option>
            </select>
          </div>

          <!-- Description -->
          <div class="form-group full-width">
            <label class="form-label">{{ t('admin.membership.shortDescLabel') }}</label>
            <input
              v-model="formDesc"
              type="text"
              class="form-input"
              :placeholder="t('admin.membership.shortDescPlaceholder')"
            />
          </div>

          <!-- Row 3: Metrics -->
          <div class="form-group">
            <label class="form-label">{{ t('admin.membership.courtDiscountLabel') }}</label>
            <input
              v-model.number="formCourtDiscount"
              type="number"
              min="0"
              max="100"
              class="form-input font-mono"
            />
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.membership.pointMultiplierLabel') }}</label>
            <input
              v-model.number="formPointMultiplier"
              type="number"
              step="0.1"
              min="1"
              max="10"
              class="form-input font-mono"
            />
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.membership.advanceDaysLabel') }}</label>
            <input
              v-model.number="formAdvanceDays"
              type="number"
              min="1"
              max="90"
              class="form-input font-mono"
            />
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('admin.membership.freeHoursLabel') }}</label>
            <input
              v-model.number="formFreeHours"
              type="number"
              min="0"
              max="50"
              class="form-input font-mono"
            />
          </div>
        </div>

        <!-- Checkboxes -->
        <div class="checkbox-row">
          <label class="checkbox-label">
            <input v-model="formWaiveCancelFee" type="checkbox" />
            <span>{{ t('admin.membership.waiveCancelFee') }}</span>
          </label>

          <label class="checkbox-label">
            <input v-model="formIsPopular" type="checkbox" />
            <span>{{ t('admin.membership.markVipPopular') }}</span>
          </label>
        </div>

        <!-- Perks Builder -->
        <div class="perks-builder">
          <label class="form-label">{{ t('admin.membership.perksLabel') }}</label>
          <div class="perks-list">
            <div v-for="(p, idx) in formPerks" :key="idx" class="perk-tag">
              <span class="material-symbols-outlined icon-16 text-primary">check_circle</span>
              <span class="perk-tag-text">{{ p }}</span>
              <button type="button" class="btn-remove-perk" @click="removePerk(idx)">
                <span class="material-symbols-outlined icon-14">close</span>
              </button>
            </div>
          </div>

          <div class="add-perk-row">
            <input
              v-model="newPerkInput"
              type="text"
              class="form-input flex-1"
              :placeholder="t('admin.membership.newPerkPlaceholder')"
              @keydown.enter.prevent="addPerk"
            />
            <button type="button" class="btn-add-perk" @click="addPerk">{{ t('admin.membership.addPerkBtn') }}</button>
          </div>
        </div>

        <!-- Versioning Alert when Editing -->
        <div v-if="plan" class="versioning-box">
          <label class="checkbox-label">
            <input v-model="createNewVersion" type="checkbox" />
            <span class="font-bold text-primary">
              {{ t('admin.membership.createNewVersionLabel', { current: plan.version, next: 'v' + (parseFloat(plan.version.replace('v','')) + 0.1).toFixed(1) }) }}
            </span>
          </label>
          <p class="versioning-desc">
            {{ t('admin.membership.versioningDesc', { count: plan.activeSubscribers }) }}
          </p>
        </div>

        <!-- Modal Footer -->
        <div class="modal-footer">
          <button type="button" class="btn-secondary" @click="emit('close')">{{ t('common.cancel') }}</button>
          <button type="submit" class="btn-primary">
            {{ plan ? t('admin.membership.saveUpdateBtn') : t('admin.membership.createPlanSubmitBtn') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(4px);
  z-index: 1200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.modal-box {
  background: var(--surface-container-lowest, #ffffff);
  border-radius: 20px;
  width: 100%;
  max-width: 680px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
  border: 1px solid var(--outline-variant, #e1e3e4);
}

:global(html.dark) .modal-box {
  background: #1e2025;
  border-color: #353942;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid var(--surface-container, #edeeef);
}

:global(html.dark) .modal-header {
  border-color: #353942;
}

.header-title-block {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header-icon {
  font-size: 28px;
  color: var(--primary, #78555e);
}

:global(html.dark) .header-icon {
  color: #f8b4c4;
}

.modal-title {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
  color: var(--on-surface, #191c1d);
}

:global(html.dark) .modal-title {
  color: #ffffff;
}

.modal-subtitle {
  margin: 2px 0 0 0;
  font-size: 12px;
  color: var(--on-surface-variant, #4f4446);
}

.btn-close {
  border: none;
  background: transparent;
  color: var(--on-surface-variant, #4f4446);
  cursor: pointer;
}

.modal-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.full-width {
  grid-column: span 2;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--on-surface, #191c1d);
}

:global(html.dark) .form-label {
  color: #e2e8f0;
}

.form-input,
.form-select {
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline-variant, #d3c3c5);
  background: var(--surface-container-lowest, #ffffff);
  color: var(--on-surface, #191c1d);
  font-size: 14px;
  outline: none;
}

:global(html.dark) .form-input,
:global(html.dark) .form-select {
  background: #262930;
  border-color: #454747;
  color: #ffffff;
}

.checkbox-row {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.perks-builder {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.perks-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.perk-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  border-radius: 8px;
  background: var(--surface-container, #edeeef);
  font-size: 12px;
  font-weight: 600;
}

:global(html.dark) .perk-tag {
  background: #2b2e36;
  color: #ffffff;
}

.btn-remove-perk {
  border: none;
  background: transparent;
  cursor: pointer;
  padding: 0;
  color: var(--on-surface-variant, #4f4446);
  display: flex;
  align-items: center;
}

.add-perk-row {
  display: flex;
  gap: 8px;
}

.btn-add-perk {
  padding: 10px 16px;
  border-radius: 10px;
  border: none;
  background: var(--surface-container-high, #e7e8e9);
  color: var(--on-surface, #191c1d);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

:global(html.dark) .btn-add-perk {
  background: #353942;
  color: #ffffff;
}

.versioning-box {
  padding: 14px 16px;
  border-radius: 12px;
  background: rgba(120, 85, 94, 0.08);
  border: 1px solid var(--primary-fixed, #ffd9e2);
}

:global(html.dark) .versioning-box {
  background: rgba(248, 180, 196, 0.08);
  border-color: #53333e;
}

.versioning-desc {
  margin: 6px 0 0 24px;
  font-size: 12px;
  color: var(--on-surface-variant, #4f4446);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding-top: 14px;
  border-top: 1px solid var(--surface-container, #edeeef);
}

:global(html.dark) .modal-footer {
  border-color: #353942;
}

.btn-secondary {
  padding: 10px 18px;
  border-radius: 10px;
  border: 1px solid var(--outline-variant, #d3c3c5);
  background: transparent;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.btn-primary {
  padding: 10px 22px;
  border-radius: 10px;
  border: none;
  background: var(--primary, #78555e);
  color: #ffffff;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.icon-16 {
  font-size: 16px;
}

.icon-14 {
  font-size: 14px;
}
</style>

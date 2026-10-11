<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { AuraButton, CurrencyInput, SelectDropdown } from '@/shared/components'
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
const formIsPopular = ref(false)
const formPerks = ref<string[]>([])
const newPerkInput = ref('')
const createNewVersion = ref(false)

const cycleOptions = computed(() => [
  { value: 'monthly', label: t('admin.membership.cycleMonthly') },
  { value: 'quarterly', label: t('admin.membership.cycleQuarterly') },
  { value: 'yearly', label: t('admin.membership.cycleYearly') },
])

watch(
  () => props.plan,
  (val) => {
    if (val) {
      formName.value = resolveLocalizedText(val.name)
      formCode.value = val.code
      formDesc.value = resolveLocalizedText(val.description)
      formPrice.value = val.price
      formCycle.value = val.billingCycle
      formCourtDiscount.value = val.courtDiscountPercent
      formPointMultiplier.value = val.pointMultiplier
      formAdvanceDays.value = val.advanceBookingDays
      formFreeHours.value = val.freeWeekendHours
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
      formIsPopular.value = false
      formPerks.value = [
        'Giảm 15% đặt sân giờ vàng',
        'Tích lũy x1.5 điểm thưởng',
        'Đặt sân trước 7 ngày',
      ]
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
      isPopular: formIsPopular.value,
      perks: formPerks.value,
    },
    createNewVersion.value,
  )
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-dialog" role="dialog" aria-modal="true">
      <div class="modal-header">
        <div class="header-left">
          <div class="modal-icon-badge">
            <span class="material-symbols-outlined">{{ plan ? 'edit_note' : 'add_circle' }}</span>
          </div>
          <div>
            <h3 class="modal-title">
              {{
                plan
                  ? t('admin.membership.editPlanTitle', { name: resolveLocalizedText(plan.name) })
                  : t('admin.membership.createPlanTitle')
              }}
            </h3>
            <p class="modal-subtitle">{{ t('admin.membership.planModalSub') }}</p>
          </div>
        </div>
        <button type="button" class="btn-close-circle" @click="emit('close')">
          <span class="material-symbols-outlined icon-18">close</span>
        </button>
      </div>

      <div class="modal-body custom-scroll">
        <form id="membership-plan-form" class="plan-form" @submit.prevent="onSubmit">
          <div class="form-card-section">
            <div class="section-title-row">
              <span class="material-symbols-outlined icon-16 text-pink">badge</span>
              <span class="section-title-txt">Thông tin gói & Biểu phí</span>
            </div>
            <div class="form-row-2">
              <div class="field-wrap">
                <label class="field-lbl required">{{ t('admin.membership.planNameLabel') }}</label>
                <input
                  v-model="formName"
                  type="text"
                  class="field-input"
                  :placeholder="t('admin.membership.planNamePlaceholder')"
                  required
                />
              </div>
              <div class="field-wrap">
                <label class="field-lbl">{{ t('admin.membership.planCodeLabel') }}</label>
                <input
                  v-model="formCode"
                  type="text"
                  class="field-input font-mono uppercase"
                  :placeholder="t('admin.membership.planCodePlaceholder')"
                />
              </div>
            </div>
            <div class="form-row-2">
              <div class="field-wrap">
                <label class="field-lbl required">{{ t('admin.membership.priceLabel') }}</label>
                <CurrencyInput v-model="formPrice" size="md" />
              </div>
              <div class="field-wrap">
                <label class="field-lbl">{{ t('admin.membership.renewalCycleLabel') }}</label>
                <SelectDropdown v-model="formCycle" :options="cycleOptions" variant="bordered" />
              </div>
            </div>
            <div class="field-wrap">
              <label class="field-lbl">{{ t('admin.membership.shortDescLabel') }}</label>
              <input
                v-model="formDesc"
                type="text"
                class="field-input"
                :placeholder="t('admin.membership.shortDescPlaceholder')"
              />
            </div>
          </div>

          <div class="form-card-section">
            <div class="section-title-row">
              <span class="material-symbols-outlined icon-16 text-pink">tune</span>
              <span class="section-title-txt">Thông số đặc quyền thành viên</span>
            </div>
            <div class="form-row-4">
              <div class="field-wrap">
                <label class="field-lbl">{{ t('admin.membership.courtDiscountLabel') }}</label>
                <div class="input-with-suffix">
                  <input
                    v-model.number="formCourtDiscount"
                    type="number"
                    min="0"
                    max="100"
                    class="field-input font-mono"
                  /><span class="input-suffix">%</span>
                </div>
              </div>
              <div class="field-wrap">
                <label class="field-lbl">{{ t('admin.membership.pointMultiplierLabel') }}</label>
                <div class="input-with-suffix">
                  <input
                    v-model.number="formPointMultiplier"
                    type="number"
                    step="0.1"
                    min="1"
                    max="10"
                    class="field-input font-mono"
                  /><span class="input-suffix">x</span>
                </div>
              </div>
              <div class="field-wrap">
                <label class="field-lbl">{{ t('admin.membership.advanceDaysLabel') }}</label>
                <div class="input-with-suffix">
                  <input
                    v-model.number="formAdvanceDays"
                    type="number"
                    min="1"
                    max="90"
                    class="field-input font-mono"
                  /><span class="input-suffix">ngày</span>
                </div>
              </div>
              <div class="field-wrap">
                <label class="field-lbl">{{ t('admin.membership.freeHoursLabel') }}</label>
                <div class="input-with-suffix">
                  <input
                    v-model.number="formFreeHours"
                    type="number"
                    min="0"
                    max="50"
                    class="field-input font-mono"
                  /><span class="input-suffix">giờ</span>
                </div>
              </div>
            </div>
            <div class="checkbox-row">
              <label class="custom-check-lbl"
                ><input v-model="formIsPopular" type="checkbox" class="check-box" /><span>{{
                  t('admin.membership.markVipPopular')
                }}</span></label
              >
            </div>
          </div>

          <div class="form-card-section">
            <div class="section-title-row">
              <span class="material-symbols-outlined icon-16 text-pink">checklist</span>
              <span class="section-title-txt">Quyền lợi hiển thị (Perks)</span>
            </div>
            <div class="perks-tags-list">
              <div v-for="(p, idx) in formPerks" :key="idx" class="perk-chip-item">
                <span class="material-symbols-outlined icon-14 text-pink">check_circle</span>
                <span class="chip-text">{{ resolveLocalizedText(p) }}</span>
                <button type="button" class="chip-del-btn" @click="removePerk(idx)">
                  <span class="material-symbols-outlined icon-14">close</span>
                </button>
              </div>
            </div>
            <div class="add-perk-inline-row">
              <input
                v-model="newPerkInput"
                type="text"
                class="field-input flex-1"
                :placeholder="t('admin.membership.newPerkPlaceholder')"
                @keydown.enter.prevent="addPerk"
              />
              <AuraButton type="button" variant="secondary" size="sm" @click="addPerk"
                ><span class="material-symbols-outlined icon-16">add</span
                ><span>{{ t('admin.membership.addPerkBtn') }}</span></AuraButton
              >
            </div>
            <div v-if="plan" class="versioning-notice-card">
              <label class="version-toggle-lbl"
                ><input v-model="createNewVersion" type="checkbox" class="check-box" /><span
                  class="version-title-text"
                  >{{
                    t('admin.membership.createNewVersionLabel', {
                      current: plan.version,
                      next: 'v' + (parseFloat(plan.version.replace('v', '')) + 0.1).toFixed(1),
                    })
                  }}</span
                ></label
              >
              <p class="version-explain-txt">
                {{ t('admin.membership.versioningDesc', { count: plan.activeSubscribers }) }}
              </p>
            </div>
          </div>
        </form>
      </div>

      <div class="modal-footer">
        <AuraButton type="button" variant="secondary" @click="emit('close')">{{
          t('admin.membership.cancelBtn')
        }}</AuraButton>
        <AuraButton type="submit" variant="primary" form="membership-plan-form"
          ><span class="material-symbols-outlined icon-18">check</span
          ><span>{{
            plan ? t('admin.membership.saveUpdateBtn') : t('admin.membership.createPlanSubmitBtn')
          }}</span></AuraButton
        >
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
.modal-dialog {
  background: var(--surface);
  border-radius: 24px;
  width: 100%;
  max-width: 700px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.4);
  border: 1px solid var(--outline);
  overflow: hidden;
}
:global(html.dark) .modal-dialog {
  background: #16181f;
  border-color: #2b2f3a;
}
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 24px;
  border-bottom: 1px solid var(--outline);
  flex-shrink: 0;
  background: rgba(219, 39, 119, 0.03);
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
  font-size: 24px;
  flex-shrink: 0;
}
.modal-title {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
  color: var(--ink);
}
.modal-subtitle {
  margin: 2px 0 0;
  font-size: 12.5px;
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
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.custom-scroll::-webkit-scrollbar {
  width: 6px;
}
.custom-scroll::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.15);
  border-radius: 4px;
}
.plan-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.form-card-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  border-radius: 16px;
  background: var(--surface-container-high, #f8f9fa);
  border: 1px solid var(--outline);
}
:global(html.dark) .form-card-section {
  background: #1c1f27;
  border-color: #282c37;
}
.section-title-row {
  display: flex;
  align-items: center;
  gap: 8px;
}
.section-title-txt {
  font-size: 12px;
  font-weight: 800;
  color: var(--ink);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.form-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.form-row-4 {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
}
.field-wrap {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.field-lbl {
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
:global(html.dark) .field-input {
  background: #14161c;
  border-color: #2d313d;
}
.field-input:focus {
  border-color: var(--pink-dark, #db2777);
}
.input-with-suffix {
  position: relative;
  display: flex;
  align-items: center;
}
.input-with-suffix .field-input {
  width: 100%;
  padding-right: 36px;
}
.input-suffix {
  position: absolute;
  right: 12px;
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
  pointer-events: none;
}
.font-mono {
  font-family: monospace;
}
.uppercase {
  text-transform: uppercase;
}
.checkbox-row {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
  margin-top: 4px;
}
.custom-check-lbl {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  color: var(--ink);
}
.check-box {
  accent-color: var(--pink-dark, #db2777);
  width: 16px;
  height: 16px;
  cursor: pointer;
}
.perks-tags-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.perk-chip-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  border-radius: 8px;
  background: var(--surface);
  border: 1px solid var(--outline);
  font-size: 12.5px;
}
:global(html.dark) .perk-chip-item {
  background: #14161c;
  border-color: #2d313d;
}
.chip-del-btn {
  border: none;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  display: flex;
  padding: 0;
}
.chip-del-btn:hover {
  color: #ef4444;
}
.add-perk-inline-row {
  display: flex;
  gap: 8px;
}
.versioning-notice-card {
  padding: 12px 14px;
  border-radius: 12px;
  background: rgba(219, 39, 119, 0.08);
  border: 1px solid rgba(219, 39, 119, 0.25);
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-top: 4px;
}
.version-toggle-lbl {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 12.5px;
  font-weight: 700;
  cursor: pointer;
}
.version-title-text {
  color: var(--pink-dark, #db2777);
}
:global(html.dark) .version-title-text {
  color: #f472b6;
}
.version-explain-txt {
  margin: 0;
  font-size: 11.5px;
  color: var(--muted);
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
.flex-1 {
  flex: 1;
}
.text-pink {
  color: var(--pink-dark, #db2777);
}
.icon-18 {
  font-size: 18px;
}
.icon-16 {
  font-size: 16px;
}
.icon-14 {
  font-size: 14px;
}
@media (max-width: 640px) {
  .form-row-2,
  .form-row-4 {
    grid-template-columns: 1fr;
  }
}
</style>

<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import type { DisputeCase, ArbitrateDisputePayload } from '../../types/dispute'

const props = defineProps<{
  show: boolean
  item: DisputeCase | null
  isSubmitting?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', payload: ArbitrateDisputePayload): void
}>()

const { t, locale } = useI18n()

const selectedRefundMethod = ref<'wallet' | 'payos_bank' | 'partial_wallet' | 'none'>('wallet')
const refundAmount = ref<number>(360000)
const selectedGoodwill = ref<'voucher_20' | 'reputation_points' | 'none'>('voucher_20')
const penaltyNotes = ref('')
const penaltyPoints = ref(2)
const arbitratorNotes = ref('')

watch(
  () => props.item,
  (newItem) => {
    if (newItem) {
      refundAmount.value = newItem.amountDisputed || 360000
      penaltyNotes.value =
        locale.value === 'en'
          ? 'Operational reminder notice issued to shift operator regarding manual override error.'
          : 'Lập biên bản nhắc nhở ca trực do lỗi ghi đè thủ công không kiểm tra đối soát.'
      arbitratorNotes.value =
        locale.value === 'en'
          ? 'Approved 100% refund to complainant due to facility webhook latency and front-desk conflict.'
          : 'Chấp thuận hoàn 100% giá trị đặt sân cho nguyên đơn do lỗi hệ thống và thao tác của cơ sở.'
    }
  },
  { immediate: true }
)

function handleSubmit() {
  const goodwillMap = {
    voucher_20: locale.value === 'en' ? '20% Discount Voucher' : 'Voucher giảm 20% đơn tiếp theo',
    reputation_points: locale.value === 'en' ? '+500 Aura Club Reputation Points' : '500 Điểm Danh Vọng Aura Rewards',
    none: locale.value === 'en' ? 'None' : 'Không bồi thường thêm',
  }

  emit('submit', {
    decision: selectedRefundMethod.value === 'none' ? 'resolve' : 'resolve_and_refund',
    refundMethod: selectedRefundMethod.value,
    refundAmount: selectedRefundMethod.value === 'none' ? 0 : refundAmount.value,
    compensationType: selectedGoodwill.value,
    compensationValue: goodwillMap[selectedGoodwill.value],
    penaltyNotes: penaltyNotes.value,
    penaltyPoints: penaltyPoints.value,
    notes: arbitratorNotes.value,
  })
}

function handleKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && props.show) {
    emit('close')
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <div>
    <!-- Backdrop -->
    <div
      v-if="show"
      class="drawer-backdrop"
      @click="emit('close')"
    ></div>

    <!-- Slide-over Panel -->
    <div
      class="arbitration-drawer"
      :class="{ 'drawer-open': show }"
      role="dialog"
      aria-modal="true"
    >
      <!-- Drawer Header -->
      <div class="drawer-header">
        <div>
          <h3 class="drawer-title">{{ t('admin.disputes.drawerTitle') }}</h3>
          <p class="drawer-sub">
            {{ t('admin.disputes.drawerSubtitle', { code: item?.disputeCode, facility: item?.facility.name }) }}
          </p>
        </div>
        <button type="button" class="btn-close" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <!-- Drawer Body -->
      <div class="drawer-body" style="scrollbar-width: none;">
        <!-- Financial Context -->
        <div class="context-card">
          <div class="context-row">
            <span class="context-lbl">{{ t('admin.disputes.totalDisputedLabel') }}</span>
            <span class="context-val text-primary font-bold">
              {{ (item?.amountDisputed || 0).toLocaleString(locale === 'en' ? 'en-US' : 'vi-VN') }} ₫
            </span>
          </div>
          <div class="context-row">
            <span class="context-lbl">{{ t('admin.disputes.beneficiaryLabel') }}</span>
            <span class="context-val">{{ item?.complainant.name }}</span>
          </div>
        </div>

        <!-- Form 1: Refund Method -->
        <div class="form-group">
          <label class="form-label">{{ t('admin.disputes.refundMethodLabel') }}</label>
          <select v-model="selectedRefundMethod" class="form-select">
            <option value="wallet">{{ t('admin.disputes.refundOptWallet') }}</option>
            <option value="payos_bank">{{ t('admin.disputes.refundOptBank') }}</option>
            <option value="partial_wallet">{{ t('admin.disputes.refundOptPartial') }}</option>
            <option value="none">{{ t('admin.disputes.refundOptNone') }}</option>
          </select>
        </div>

        <!-- Form 2: Goodwill Compensation -->
        <div class="form-group">
          <label class="form-label">{{ t('admin.disputes.goodwillLabel') }}</label>
          <div class="radio-options">
            <label class="radio-card" :class="{ selected: selectedGoodwill === 'voucher_20' }">
              <input
                v-model="selectedGoodwill"
                type="radio"
                value="voucher_20"
                class="radio-input"
              />
              <div class="radio-text">
                <span class="radio-title">{{ t('admin.disputes.goodwillVoucher20Title') }}</span>
                <span class="radio-desc">{{ t('admin.disputes.goodwillVoucher20Desc') }}</span>
              </div>
            </label>

            <label class="radio-card" :class="{ selected: selectedGoodwill === 'reputation_points' }">
              <input
                v-model="selectedGoodwill"
                type="radio"
                value="reputation_points"
                class="radio-input"
              />
              <div class="radio-text">
                <span class="radio-title">{{ t('admin.disputes.goodwillPointsTitle') }}</span>
                <span class="radio-desc">{{ t('admin.disputes.goodwillPointsDesc') }}</span>
              </div>
            </label>

            <label class="radio-card" :class="{ selected: selectedGoodwill === 'none' }">
              <input
                v-model="selectedGoodwill"
                type="radio"
                value="none"
                class="radio-input"
              />
              <div class="radio-text">
                <span class="radio-title">{{ t('admin.disputes.goodwillNoneTitle') }}</span>
                <span class="radio-desc">{{ t('admin.disputes.goodwillNoneDesc') }}</span>
              </div>
            </label>
          </div>
        </div>

        <!-- Form 3: Facility Penalty Notes -->
        <div class="form-group">
          <label class="form-label">{{ t('admin.disputes.penaltyNotesLabel') }}</label>
          <textarea
            v-model="penaltyNotes"
            class="form-textarea"
            :placeholder="t('admin.disputes.penaltyNotesPlaceholder')"
            rows="3"
          ></textarea>
        </div>

        <!-- Form 4: Arbitrator Notes -->
        <div class="form-group">
          <label class="form-label">{{ t('admin.disputes.arbitratorNotesLabel') }}</label>
          <textarea
            v-model="arbitratorNotes"
            class="form-textarea"
            :placeholder="t('admin.disputes.arbitratorNotesPlaceholder')"
            rows="3"
          ></textarea>
        </div>

        <!-- E-Signature Verification Badge -->
        <div class="signature-box">
          <span class="material-symbols-outlined sig-icon">verified_user</span>
          <div class="sig-details">
            <span class="sig-title">{{ t('admin.disputes.signatureTitle') }}</span>
            <span class="sig-hash">{{ t('admin.disputes.signatureHash', { hash: '8f9a2c...b109e4' }) }}</span>
          </div>
        </div>
      </div>

      <!-- Drawer Footer -->
      <div class="drawer-footer">
        <button
          type="button"
          class="btn-cancel"
          :disabled="isSubmitting"
          @click="emit('close')"
        >
          {{ t('admin.disputes.btnCancel') }}
        </button>
        <button
          type="button"
          class="btn-submit"
          :disabled="isSubmitting"
          @click="handleSubmit"
        >
          <span class="material-symbols-outlined icon-18">check_circle</span>
          <span>{{ isSubmitting ? t('admin.disputes.btnSubmitting') : t('admin.disputes.btnSubmitArbitration') }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.drawer-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(20, 17, 19, 0.6);
  backdrop-filter: blur(6px);
  z-index: 999;
  animation: fadeIn 0.2s ease-out;
}

.arbitration-drawer {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  width: 500px;
  max-width: 90vw;
  background: var(--surface);
  z-index: 1000;
  box-shadow: -4px 0 24px rgba(0, 0, 0, 0.3);
  display: flex;
  flex-direction: column;
  transform: translateX(100%);
  transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  color: var(--ink);
}

.drawer-open {
  transform: translateX(0);
}

.drawer-header {
  padding: 20px 24px;
  border-bottom: 1px solid var(--outline);
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  background: var(--surface-subtle);
}

.drawer-title {
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
  margin: 0;
}

.drawer-sub {
  font-size: 12px;
  color: var(--muted);
  margin: 4px 0 0;
}

.btn-close {
  background: transparent;
  border: none;
  color: var(--muted);
  cursor: pointer;
  padding: 4px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-close:hover {
  background: var(--soft);
  color: var(--ink);
}

.drawer-body {
  padding: 24px;
  overflow-y: auto;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.context-card {
  background: var(--surface-subtle);
  border: 1px solid var(--outline);
  border-radius: 12px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.context-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
}

.context-lbl {
  color: var(--muted);
}

.context-val {
  color: var(--ink);
  font-weight: 600;
}

.text-primary {
  color: var(--primary);
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-label {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
}

.form-select,
.form-textarea {
  background: var(--surface-subtle);
  border: 1px solid var(--outline);
  border-radius: 10px;
  padding: 10px 14px;
  font-size: 13px;
  color: var(--ink);
  outline: none;
  font-family: inherit;
  transition: border-color 0.2s;
}

.form-select:focus,
.form-textarea:focus {
  border-color: var(--primary);
  background: var(--surface);
}

.radio-options {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.radio-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border: 1px solid var(--outline);
  border-radius: 10px;
  background: var(--surface-subtle);
  cursor: pointer;
  transition: all 0.2s;
}

.radio-card:hover {
  background: var(--soft);
}

.radio-card.selected {
  background: var(--primary-container);
  border-color: var(--primary);
}

.radio-input {
  accent-color: var(--primary);
}

.radio-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.radio-title {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
}

.radio-desc {
  font-size: 11px;
  color: var(--muted);
}

.signature-box {
  background: rgba(168, 85, 247, 0.15);
  border: 1px solid rgba(168, 85, 247, 0.3);
  border-radius: 10px;
  padding: 12px 16px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.sig-icon {
  font-size: 24px;
  color: #c084fc;
}

.sig-details {
  display: flex;
  flex-direction: column;
}

.sig-title {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink);
}

.sig-hash {
  font-size: 10px;
  color: #c084fc;
  font-family: monospace;
}

.drawer-footer {
  padding: 16px 24px;
  border-top: 1px solid var(--outline);
  background: var(--surface);
  display: flex;
  gap: 12px;
}

.btn-cancel {
  flex: 1;
  background: var(--surface-subtle);
  border: 1px solid var(--outline);
  border-radius: 10px;
  padding: 10px 16px;
  font-size: 13px;
  font-weight: 600;
  color: var(--muted);
  cursor: pointer;
}

.btn-cancel:hover:not(:disabled) {
  background: var(--soft);
  color: var(--ink);
}

.btn-submit {
  flex: 2;
  background: var(--primary);
  border: none;
  border-radius: 10px;
  padding: 10px 18px;
  font-size: 13px;
  font-weight: 700;
  color: var(--on-primary, #ffffff);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  box-shadow: 0 2px 8px rgba(120, 85, 94, 0.25);
  transition: all 0.2s;
}

.btn-submit:hover:not(:disabled) {
  background: var(--primary-hover);
}

button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.icon-18 {
  font-size: 18px;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
</style>

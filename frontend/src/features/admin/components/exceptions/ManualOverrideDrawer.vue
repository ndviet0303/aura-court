<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency, resolveLocalizedText } from '@/i18n'
import type { FinancialException } from '../../types/exceptions'

const props = defineProps<{
  show: boolean
  item: FinancialException | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', payload: { actionType: string; reason: string }): void
}>()

const { t } = useI18n()

const actionType = ref(t('admin.exceptions.optManualRefund'))
const reason = ref('')
const agreed = ref(false)

watch(
  () => props.show,
  (val) => {
    if (val) {
      reason.value = ''
      agreed.value = false
      actionType.value = t('admin.exceptions.optManualRefund')
    }
  }
)

function handleSubmit() {
  if (reason.value.trim().length < 20) {
    alert(t('admin.exceptions.auditReasonPlaceholder'))
    return
  }
  if (!agreed.value) {
    alert(t('admin.exceptions.legalCommitment'))
    return
  }
  emit('submit', { actionType: actionType.value, reason: reason.value.trim() })
}
</script>

<template>
  <div v-if="show" class="drawer-backdrop" @click="emit('close')"></div>
  <div class="override-drawer" :class="{ 'is-open': show }">
    <div class="drawer-header">
      <div>
        <h3 class="drawer-title">{{ t('admin.exceptions.overrideDrawerTitle') }}</h3>
        <p class="drawer-sub">{{ t('admin.exceptions.overrideDrawerSub') }}</p>
      </div>
      <button class="btn-close-drawer" type="button" @click="emit('close')">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>

    <div v-if="item" class="drawer-body">
      <!-- Target summary card -->
      <div class="target-summary-box">
        <div class="summary-row">
          <span class="summary-label">{{ t('admin.exceptions.txCodeLabel') }}</span>
          <span class="font-mono font-bold">{{ item.reference }}</span>
        </div>
        <div class="summary-row">
          <span class="summary-label">{{ t('admin.exceptions.entityLabel') }}</span>
          <span>{{ resolveLocalizedText(item.resolver?.name || t('admin.schedule.systemUser')) }} ({{ (item.resolver?.id || '') }})</span>
        </div>
        <div class="summary-row">
          <span class="summary-label">{{ t('admin.exceptions.requestedAmountLabel') }}</span>
          <span class="font-mono font-bold text-critical">{{ formatCurrency(item.amount) }}</span>
        </div>
        <div class="summary-row">
          <span class="summary-label">{{ t('admin.exceptions.alertReasonLabel') }}</span>
          <span class="reason-pill">{{ resolveLocalizedText(item.description) }}</span>
        </div>
      </div>

      <!-- Form fields -->
      <div class="form-group">
        <label class="form-label">{{ t('admin.exceptions.actionTypeLabel') }} <span class="text-critical">*</span></label>
        <select v-model="actionType" class="form-select">
          <option :value="t('admin.exceptions.optManualRefund')">{{ t('admin.exceptions.optManualRefund') }}</option>
          <option :value="t('admin.exceptions.optWalletRebalance')">{{ t('admin.exceptions.optWalletRebalance') }}</option>
          <option :value="t('admin.exceptions.optUnflagWithdrawal')">{{ t('admin.exceptions.optUnflagWithdrawal') }}</option>
          <option :value="t('admin.exceptions.optCancelAndLock')">{{ t('admin.exceptions.optCancelAndLock') }}</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">{{ t('admin.exceptions.auditReasonLabel') }} <span class="text-critical">*</span></label>
        <textarea
          v-model="reason"
          :placeholder="t('admin.exceptions.auditReasonPlaceholder')"
          class="form-textarea"
          rows="4"
        ></textarea>
        <span class="char-count font-mono">{{ t('admin.exceptions.minCharsCount', { count: reason.length }) }}</span>
      </div>

      <!-- E-Signature box -->
      <div class="form-group">
        <label class="form-label">{{ t('admin.exceptions.signatureTitle') }}</label>
        <div class="signature-canvas-box">
          <span class="sign-text">
            <span class="material-symbols-outlined icon-16">draw</span>
            <span>{{ t('admin.exceptions.signatureVerified') }}</span>
          </span>
        </div>
      </div>

      <!-- Legal commitment checkbox -->
      <div class="legal-box">
        <input id="agreement" v-model="agreed" type="checkbox" class="legal-checkbox" />
        <label for="agreement" class="legal-label">
          {{ t('admin.exceptions.legalCommitment') }}
        </label>
      </div>
    </div>

    <div class="drawer-footer">
      <button class="btn-cancel" type="button" @click="emit('close')">{{ t('common.close') }}</button>
      <button class="btn-confirm-override" type="button" @click="handleSubmit">
        <span class="material-symbols-outlined icon-16">verified</span>
        <span>{{ t('admin.exceptions.signAndRecordBtn') }}</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.drawer-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  z-index: 100;
}

.override-drawer {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  width: 480px;
  max-width: 90vw;
  background: #ffffff;
  z-index: 101;
  box-shadow: -4px 0 24px rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
  transform: translateX(100%);
  transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.override-drawer.is-open {
  transform: translateX(0);
}

.drawer-header {
  padding: 20px;
  border-bottom: 1px solid #e1e3e4;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.drawer-title {
  font-size: 17px;
  font-weight: 700;
  margin: 0;
}

.drawer-sub {
  font-size: 12px;
  color: #817476;
  margin: 4px 0 0;
}

.btn-close-drawer {
  background: transparent;
  border: none;
  cursor: pointer;
  color: #817476;
}

.drawer-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  overflow-y: auto;
  flex: 1;
}

.target-summary-box {
  background: #f8f9fa;
  border: 1px solid #e1e3e4;
  border-radius: 12px;
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  font-size: 13px;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.summary-label {
  color: #817476;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 13px;
  font-weight: 600;
  color: #191c1d;
}

.form-select,
.form-textarea {
  width: 100%;
  border: 1px solid #e1e3e4;
  border-radius: 8px;
  padding: 10px 12px;
  font-size: 13px;
  outline: none;
  font-family: inherit;
}

.form-select:focus,
.form-textarea:focus {
  border-color: #78555e;
}

.char-count {
  font-size: 11px;
  color: #817476;
  text-align: right;
}

.signature-canvas-box {
  border: 1px dashed var(--border);
  border-radius: 8px;
  background: var(--surface-subtle);
  padding: 16px;
  text-align: center;
  font-size: 13px;
  color: #78555e;
  font-weight: 600;
}

.sign-text {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.legal-box {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  background: #fff1f2;
  border: 1px solid #ffe4e6;
  border-radius: 8px;
  padding: 10px;
}

.legal-checkbox {
  margin-top: 2px;
}

.legal-label {
  font-size: 12px;
  color: #9f1239;
  line-height: 1.4;
  cursor: pointer;
}

.drawer-footer {
  padding: 16px 20px;
  border-top: 1px solid #e1e3e4;
  display: flex;
  gap: 12px;
}

.btn-cancel {
  flex: 1;
  padding: 10px;
  border-radius: 8px;
  border: 1px solid #e1e3e4;
  background: #ffffff;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.btn-confirm-override {
  flex: 2;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px;
  border-radius: 8px;
  border: none;
  background: #78555e;
  color: #ffffff;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 0.2s;
}

.btn-confirm-override:hover {
  opacity: 0.9;
}

.text-critical { color: #ba1a1a; }
.icon-16 { font-size: 16px; }
</style>

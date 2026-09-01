<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { AuraButton } from '@/shared/components'
import type { RecurringContractItem, ContractInvoice } from '../../types/contract'

const props = defineProps<{
  show: boolean
  target: { contract: RecurringContractItem; invoice: ContractInvoice } | null
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'confirm', payload: { seriesId: number; invoiceId: number; reference: string }): void
}>()

const { t } = useI18n()

const paymentReference = ref('')

function handleConfirm() {
  if (!props.target) return
  const refCode = paymentReference.value.trim() || `REC-${Date.now().toString().slice(-6)}`
  emit('confirm', {
    seriesId: props.target.contract.id,
    invoiceId: props.target.invoice.id,
    reference: refCode,
  })
}
</script>

<template>
  <div v-if="show && target" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon text-pink">payments</span>
          <h3 class="modal-title">{{ t('admin.contracts.confirmPaymentTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <p class="desc-txt">{{ t('admin.contracts.confirmPaymentDesc') }}</p>

        <div class="invoice-summary-box">
          <div class="summary-row">
            <span class="lbl">{{ t('admin.contracts.customerClubLabel') }}</span>
            <strong>{{ target.contract.customer_name }}</strong>
          </div>
          <div class="summary-row">
            <span class="lbl">{{ t('admin.contracts.hdrContractAndCustomer') }}</span>
            <span class="font-mono">{{ target.contract.contract_code }}</span>
          </div>
          <div class="summary-row">
            <span class="lbl">{{ t('admin.contracts.hdrAmount') }}</span>
            <strong class="text-pink font-mono">{{ formatCurrency(target.invoice.amount) }}</strong>
          </div>
        </div>

        <div class="form-group mt-3">
          <label class="form-label">{{ t('admin.contracts.paymentReferenceLabel') }}</label>
          <input
            v-model="paymentReference"
            type="text"
            class="form-input font-mono"
            :placeholder="t('admin.contracts.paymentRefPlaceholder')"
          />
        </div>
      </div>

      <div class="modal-footer">
        <AuraButton variant="outline" @click="emit('close')">
          {{ t('common.cancel') }}
        </AuraButton>
        <AuraButton
          variant="primary"
          class="btn-primary-pink"
          :disabled="loading"
          @click="handleConfirm"
        >
          <span v-if="loading" class="material-symbols-outlined icon-18 spin-icon">progress_activity</span>
          <span v-else class="material-symbols-outlined icon-18">check_circle</span>
          <span>{{ t('admin.contracts.confirmAndMarkPaid') }}</span>
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.modal-card {
  background: var(--surface);
  border-radius: 18px;
  border: 1px solid var(--outline);
  width: 100%;
  max-width: 480px;
  display: flex;
  flex-direction: column;
  box-shadow: 0 16px 36px rgba(0, 0, 0, 0.18);
  overflow: hidden;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 20px;
  border-bottom: 1px solid var(--outline);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-title-icon {
  font-size: 22px;
}

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
}

.modal-close-btn {
  background: none;
  border: none;
  font-size: 18px;
  color: var(--muted);
  cursor: pointer;
}

.modal-body {
  padding: 20px;
}

.desc-txt {
  margin: 0 0 14px;
  font-size: 13px;
  color: var(--muted);
}

.invoice-summary-box {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 14px;
  border-radius: 10px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
}

.lbl {
  color: var(--muted);
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink);
}

.form-input {
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  outline: none;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 20px;
  border-top: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.01));
}

.btn-primary-pink {
  background: var(--pink-dark, #db2777) !important;
  color: #fff !important;
}

.mt-3 { margin-top: 12px; }
.icon-18 { font-size: 18px; }
.spin-icon { animation: spin 1s linear infinite; }

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>

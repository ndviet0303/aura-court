<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { OrderReceipt } from '../types/pos'

defineProps<{
  show: boolean
  receipt: OrderReceipt | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'print'): void
}>()

const { t } = useI18n()

function formatMoney(val: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0)
}
</script>

<template>
  <div v-if="show && receipt" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-sm">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined modal-icon">receipt_long</span>
          <h3 class="modal-title">{{ t('admin.pos.receiptTitle') }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div class="modal-body receipt-printable">
        <div class="receipt-brand">
          <h4>AURA COURT SYSTEM</h4>
          <p class="receipt-sub">{{ t('admin.pos.receiptSubtitle') }}</p>
          <p class="receipt-time">{{ receipt.created_at }}</p>
          <p class="receipt-code">
            {{ t('admin.pos.receiptOrderCode') }}: <strong>{{ receipt.order_code }}</strong>
          </p>
        </div>

        <div class="receipt-meta-info">
          <p>
            {{ t('admin.pos.receiptCashier') }}: <strong>{{ receipt.cashier_name }}</strong>
          </p>
          <p v-if="receipt.customer_name">
            {{ t('admin.pos.receiptCustomer') }}: <strong>{{ receipt.customer_name }}</strong>
          </p>
          <p v-if="receipt.court_info">
            {{ t('admin.pos.receiptCourt') }}: <strong>{{ receipt.court_info }}</strong>
          </p>
          <p>
            {{ t('admin.pos.receiptMethod') }}:
            <strong>{{ receipt.payment_method.toUpperCase() }}</strong>
          </p>
        </div>

        <hr class="receipt-divider" />

        <div class="receipt-items-table">
          <div class="receipt-item-header">
            <span>{{ t('admin.pos.receiptHdrItem') }}</span>
            <span>{{ t('admin.pos.receiptHdrQty') }}</span>
            <span>{{ t('admin.pos.receiptHdrTotal') }}</span>
          </div>
          <div v-for="(item, idx) in receipt.items" :key="idx" class="receipt-item-line">
            <span class="item-name">
              {{ item.name }}
              <small v-if="item.vat_rate !== undefined" class="receipt-item-vat"
                >({{ Math.round(item.vat_rate * 100) }}% VAT)</small
              >
            </span>
            <span class="item-qty">x{{ item.quantity }}</span>
            <span class="item-tot">{{ formatMoney(item.total) }}</span>
          </div>
        </div>

        <hr class="receipt-divider" />

        <div class="receipt-calculations">
          <div class="calc-row">
            <span>{{ t('admin.pos.subtotal') }}</span>
            <span>{{ formatMoney(receipt.subtotal) }}</span>
          </div>
          <div v-if="receipt.discount_amount > 0" class="calc-row discount">
            <span>{{ t('admin.pos.receiptDiscount') }}:</span>
            <span>-{{ formatMoney(receipt.discount_amount) }}</span>
          </div>
          <template v-if="receipt.vat_breakdown && receipt.vat_breakdown.length > 0">
            <div v-for="b in receipt.vat_breakdown" :key="b.rate" class="calc-row">
              <span>{{ t('admin.pos.receiptVat', { percent: b.percent }) }}:</span>
              <span>+{{ formatMoney(b.amount) }}</span>
            </div>
          </template>
          <div v-else-if="receipt.tax_amount > 0" class="calc-row">
            <span>{{ t('admin.pos.receiptVat', { percent: receipt.vat_percent || 8 }) }}:</span>
            <span>+{{ formatMoney(receipt.tax_amount) }}</span>
          </div>
          <div class="calc-row final-total">
            <strong>{{ t('admin.pos.receiptGrandTotal') }}:</strong>
            <strong>{{ formatMoney(receipt.grand_total) }}</strong>
          </div>
          <div v-if="receipt.cash_tendered" class="calc-row">
            <span>{{ t('admin.pos.cashTenderedLabel') }}</span>
            <span>{{ formatMoney(receipt.cash_tendered) }}</span>
          </div>
          <div v-if="receipt.cash_change" class="calc-row">
            <span>{{ t('admin.pos.cashChangeLabel') }}</span>
            <span>{{ formatMoney(receipt.cash_change) }}</span>
          </div>
        </div>

        <div class="receipt-footer-msg">
          <p>{{ t('admin.pos.receiptThankYou') }}</p>
          <p class="wifi-info">{{ t('admin.pos.receiptWifiInfo') }}</p>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" @click="emit('close')">
          {{ t('admin.pos.cancel') }}
        </button>
        <button type="button" class="btn-primary" @click="emit('print')">
          <span class="material-symbols-outlined icon-18">print</span>
          <span>{{ t('admin.pos.printReceiptBtn') }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;
  padding: 16px;
}

.modal-card {
  width: 100%;
  max-width: 440px;
  background: var(--surface, #ffffff);
  border-radius: 16px;
  border: 1px solid var(--outline, #e2e8f0);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
  overflow: hidden;
  color: var(--ink, #0f172a);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 18px;
  border-bottom: 1px solid var(--outline, #e2e8f0);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.modal-icon {
  color: var(--primary, #10b981);
  font-size: 22px;
}

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: var(--ink, #0f172a);
}

.modal-close-btn {
  background: transparent;
  border: none;
  color: var(--muted, #64748b);
  cursor: pointer;
  display: flex;
  align-items: center;
}

.modal-body {
  padding: 18px;
  max-height: 70vh;
  overflow-y: auto;
}

.receipt-printable {
  font-family: 'Courier New', Courier, monospace;
  background: #ffffff;
  color: #0f172a;
  padding: 16px;
  border: 1px dashed #cbd5e1;
  border-radius: 8px;
  font-size: 13px;
}

.receipt-brand {
  text-align: center;
  margin-bottom: 12px;
}

.receipt-brand h4 {
  margin: 0 0 4px;
  font-size: 15px;
  font-weight: 800;
  letter-spacing: 1px;
}

.receipt-sub {
  margin: 0 0 4px;
  font-size: 11px;
  color: #64748b;
}

.receipt-time,
.receipt-code {
  margin: 2px 0;
  font-size: 12px;
}

.receipt-meta-info {
  margin-top: 8px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.receipt-meta-info p {
  margin: 0;
  font-size: 12px;
}

.receipt-divider {
  border: none;
  border-top: 1px dashed #cbd5e1;
  margin: 10px 0;
}

.receipt-item-header {
  display: flex;
  justify-content: space-between;
  font-weight: bold;
  font-size: 12px;
  margin-bottom: 6px;
}

.receipt-item-line {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 4px;
  font-size: 12px;
}

.receipt-item-line .item-name {
  flex: 1;
}

.receipt-item-vat {
  font-size: 10px;
  color: #64748b;
  margin-left: 4px;
}

.receipt-item-line .item-qty {
  width: 40px;
  text-align: center;
}

.receipt-item-line .item-tot {
  width: 90px;
  text-align: right;
  font-weight: 600;
}

.receipt-calculations {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 12px;
}

.receipt-calculations .calc-row {
  display: flex;
  justify-content: space-between;
}

.receipt-calculations .final-total {
  font-size: 14px;
  font-weight: 800;
  padding-top: 6px;
  margin-top: 4px;
  border-top: 1px solid #cbd5e1;
}

.receipt-calculations .discount {
  color: #10b981;
}

.receipt-footer-msg {
  margin-top: 16px;
  text-align: center;
  font-size: 12px;
  color: #64748b;
}

.receipt-footer-msg p {
  margin: 2px 0;
}

.wifi-info {
  font-size: 11px;
  color: #94a3b8;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 18px;
  border-top: 1px solid var(--outline, #e2e8f0);
}

.btn-cancel {
  padding: 8px 14px;
  background: var(--surface-hover, rgba(0, 0, 0, 0.05));
  border: 1px solid var(--outline, #cbd5e1);
  border-radius: 8px;
  color: var(--ink, #0f172a);
  font-weight: 600;
  cursor: pointer;
}

.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  background: var(--primary, #10b981);
  border: none;
  border-radius: 8px;
  color: #ffffff;
  font-weight: 700;
  cursor: pointer;
}

.icon-18 {
  font-size: 18px;
}
</style>

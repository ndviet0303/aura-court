<script setup lang="ts">
import type { OrderReceipt } from '../types/pos'

defineProps<{
  show: boolean
  receipt: OrderReceipt | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'print'): void
}>()

function formatMoney(val: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0)
}
</script>

<template>
  <div v-if="show && receipt" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-sm">
      <div class="modal-header">
        <h3 class="modal-title">🧾 Hoá Đơn Thanh Toán</h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body receipt-printable">
        <div class="receipt-brand">
          <h4>AURA COURT SYSTEM</h4>
          <p class="receipt-sub">Trung Tâm Thể Thao Cầu Lông Chuyên Nghiệp</p>
          <p class="receipt-time">{{ receipt.created_at }}</p>
          <p class="receipt-code">Mã đơn: <strong>{{ receipt.order_code }}</strong></p>
        </div>

        <div class="receipt-meta-info">
          <p>Thu ngân: <strong>{{ receipt.cashier_name }}</strong></p>
          <p v-if="receipt.customer_name">Khách hàng: <strong>{{ receipt.customer_name }}</strong></p>
          <p v-if="receipt.court_info">Sân: <strong>{{ receipt.court_info }}</strong></p>
          <p>Phương thức: <strong>{{ receipt.payment_method.toUpperCase() }}</strong></p>
        </div>

        <hr class="receipt-divider" />

        <div class="receipt-items-table">
          <div class="receipt-item-header">
            <span>Tên món</span>
            <span>SL</span>
            <span>T.Tiền</span>
          </div>
          <div
            v-for="(item, idx) in receipt.items"
            :key="idx"
            class="receipt-item-line"
          >
            <span class="item-name">{{ item.name }}</span>
            <span class="item-qty">x{{ item.quantity }}</span>
            <span class="item-tot">{{ formatMoney(item.total) }}</span>
          </div>
        </div>

        <hr class="receipt-divider" />

        <div class="receipt-calculations">
          <div class="calc-row">
            <span>Tiền hàng:</span>
            <span>{{ formatMoney(receipt.subtotal) }}</span>
          </div>
          <div v-if="receipt.discount_amount > 0" class="calc-row discount">
            <span>Chiết khấu:</span>
            <span>-{{ formatMoney(receipt.discount_amount) }}</span>
          </div>
          <div v-if="receipt.tax_amount > 0" class="calc-row">
            <span>VAT (8%):</span>
            <span>+{{ formatMoney(receipt.tax_amount) }}</span>
          </div>
          <div class="calc-row final-total">
            <strong>TỔNG CỘNG:</strong>
            <strong>{{ formatMoney(receipt.grand_total) }}</strong>
          </div>
          <div v-if="receipt.cash_tendered" class="calc-row">
            <span>Tiền khách đưa:</span>
            <span>{{ formatMoney(receipt.cash_tendered) }}</span>
          </div>
          <div v-if="receipt.cash_change" class="calc-row">
            <span>Tiền thừa:</span>
            <span>{{ formatMoney(receipt.cash_change) }}</span>
          </div>
        </div>

        <div class="receipt-footer-msg">
          <p>Cảm ơn quý khách & Hẹn gặp lại!</p>
          <p class="wifi-info">Wifi: AuraCourt_5G / Pass: 88888888</p>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Đóng (Esc)</button>
        <button class="btn-primary" @click="emit('print')">🖨️ In Hoá Đơn</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.receipt-printable {
  font-family: 'Courier New', Courier, monospace;
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
}

.receipt-brand {
  text-align: center;
  margin-bottom: 12px;
}

.receipt-brand h4 {
  margin: 0 0 4px;
  font-size: 16px;
  letter-spacing: 1px;
}

.receipt-sub {
  margin: 0;
  font-size: 11px;
  color: var(--muted);
}

.receipt-time,
.receipt-code {
  margin: 4px 0 0;
  font-size: 12px;
}

.receipt-meta-info {
  margin-bottom: 8px;
  font-size: 12px;
}

.receipt-meta-info p {
  margin: 2px 0;
}

.receipt-divider {
  border: 0;
  border-top: 1px dashed var(--outline);
  margin: 10px 0;
}

.receipt-item-header {
  display: grid;
  grid-template-columns: 1fr 35px 80px;
  font-weight: 700;
  padding-bottom: 4px;
  border-bottom: 1px solid var(--outline);
}

.receipt-item-line {
  display: grid;
  grid-template-columns: 1fr 35px 80px;
  padding: 4px 0;
}

.receipt-item-header span:last-child,
.receipt-item-line span:last-child {
  text-align: right;
}

.receipt-calculations {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.calc-row {
  display: flex;
  justify-content: space-between;
}

.final-total {
  font-size: 15px;
  padding-top: 4px;
  border-top: 1px solid var(--outline);
}

.receipt-footer-msg {
  text-align: center;
  margin-top: 16px;
  font-size: 11px;
  color: var(--muted);
}

.btn-cancel {
  padding: 10px 16px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}

.btn-primary {
  padding: 10px 20px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
</style>

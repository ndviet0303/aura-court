<script setup lang="ts">
import type { RecurringContract } from '../../composables/useAdminContracts'

defineProps<{
  show: boolean
  contract: RecurringContract | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div v-if="show && contract" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-lg">
      <div class="modal-header">
        <h3 class="modal-title">
          📋 Chi Tiết Hợp Đồng: {{ contract.contract_code }}
        </h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="contract-meta-grid">
          <div class="meta-item">
            <span class="meta-lbl">Khách hàng / CLB:</span>
            <strong>{{ contract.customer_name }} ({{ contract.phone }})</strong>
          </div>
          <div class="meta-item">
            <span class="meta-lbl">Sân đặt định kỳ:</span>
            <strong>{{ contract.court_name }} - {{ contract.venue_name }}</strong>
          </div>
          <div class="meta-item">
            <span class="meta-lbl">Lịch & Khung giờ:</span>
            <strong>{{ contract.weekday }} · {{ contract.time_slot }}</strong>
          </div>
          <div class="meta-item">
            <span class="meta-lbl">Thời hạn hợp đồng:</span>
            <strong>{{ contract.start_date }} ➔ {{ contract.end_date }}</strong>
          </div>
        </div>

        <div class="cycles-history-box">
          <h4 class="cycles-title">Lịch Sử Các Kỳ Hóa Đơn Hàng Tháng</h4>
          <div class="cycles-table">
            <div class="cycles-header">
              <span>Kỳ Hoá Đơn</span>
              <span>Hạn Đóng</span>
              <span>Số Tiền</span>
              <span>Trạng Thái</span>
              <span>Mã Thu</span>
            </div>
            <div
              v-for="cycle in contract.payment_cycles"
              :key="cycle.id"
              class="cycle-row"
            >
              <strong>{{ cycle.cycle_name }}</strong>
              <span>{{ cycle.due_date }}</span>
              <strong class="text-pink">{{ formatMoney(cycle.amount) }}</strong>
              <span
                class="cycle-status"
                :class="cycle.status === 'paid' ? 'paid' : 'overdue'"
              >
                ● {{ cycle.status === 'paid' ? 'Đã thanh toán' : 'Chưa đóng' }}
              </span>
              <span class="font-mono">{{ cycle.invoice_code }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Đóng</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.contract-meta-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  padding: 16px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  margin-bottom: 20px;
}

.meta-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 13px;
}

.meta-lbl {
  font-size: 11px;
  color: var(--muted);
}

.cycles-history-box {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.cycles-title {
  margin: 0;
  font-size: 14px;
  font-weight: 700;
}

.cycles-table {
  display: flex;
  flex-direction: column;
  border-radius: 10px;
  border: 1px solid var(--outline);
  overflow: hidden;
}

.cycles-header {
  display: grid;
  grid-template-columns: 1.5fr 1fr 1.2fr 1.2fr 1.5fr;
  padding: 10px 14px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border-bottom: 1px solid var(--outline);
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
}

.cycle-row {
  display: grid;
  grid-template-columns: 1.5fr 1fr 1.2fr 1.2fr 1.5fr;
  padding: 10px 14px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
}

.cycle-row:last-child {
  border-bottom: 0;
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.cycle-status.paid { color: #10b981; font-weight: 700; }
.cycle-status.overdue { color: #ef4444; font-weight: 700; }

.btn-cancel {
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}
</style>

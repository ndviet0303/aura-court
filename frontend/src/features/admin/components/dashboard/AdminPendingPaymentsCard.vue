<script setup lang="ts">
import type { ReviewPayment } from '../../composables/useAdminDashboard'

defineProps<{
  payments: ReviewPayment[]
}>()

const emit = defineEmits<{
  (e: 'verify', payment: ReviewPayment): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="pending-payments-card">
    <div class="card-header">
      <h3 class="title">💳 Chuyển Khoản Chờ Xác Minh Thủ Công ({{ payments.length }})</h3>
      <span class="sub">Đối soát giao dịch ngân hàng</span>
    </div>

    <div v-if="payments.length === 0" class="empty-pay">
      Không có giao dịch nào đang chờ đối soát.
    </div>

    <div v-else class="payments-list">
      <div v-for="p in payments" :key="p.id" class="pay-item">
        <div class="p-info">
          <strong class="font-mono text-pink">{{ formatMoney(p.amount) }}</strong>
          <span class="transfer-code font-mono">Nội dung: {{ p.transfer_code }}</span>
          <span class="booking-ref">Đơn: {{ p.booking?.code }} ({{ p.booking?.user?.name }})</span>
        </div>

        <button class="btn-verify" @click="emit('verify', p)">
          ✓ Duyệt Đã Nhận Tiền
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pending-payments-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
}

.sub {
  font-size: 12px;
  color: var(--muted);
}

.empty-pay {
  padding: 20px;
  text-align: center;
  font-size: 12px;
  color: var(--muted);
}

.payments-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.pay-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.p-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 12px;
}

.text-pink { color: var(--pink-dark, #db2777); font-size: 14px; }
.transfer-code { color: var(--muted); }
.booking-ref { font-weight: 600; }

.btn-verify {
  padding: 6px 14px;
  border-radius: 8px;
  border: 0;
  background: #10b981;
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}
</style>

<script setup lang="ts">
import type { Refund } from '@/features/booking/types/booking'

defineProps<{
  refunds: Refund[]
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'approve', refundId: number): void
  (e: 'reject', refundId: number): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="refunds-panel-container">
    <div class="refunds-header">
      <div>
        <h3 class="panel-headline">💳 Yêu Cầu Hoàn Tiền Chờ Xử Lý</h3>
        <p class="panel-sub">Duyệt các yêu cầu huỷ đơn đặt sân và hoàn tiền tự động về ví / tài khoản khách.</p>
      </div>
      <span class="refund-count-badge">{{ refunds.length }} yêu cầu</span>
    </div>

    <div v-if="refunds.length === 0" class="empty-refunds-box">
      <span>🎉</span>
      <p>Không có yêu cầu hoàn tiền nào đang chờ duyệt.</p>
    </div>

    <div v-else class="refunds-list">
      <div v-for="ref in refunds" :key="ref.id" class="refund-card">
        <div class="ref-info-col">
          <div class="ref-title-row">
            <span class="ref-booking-code font-mono">{{ ref.booking?.code }}</span>
            <strong class="ref-amount text-pink">{{ formatMoney(ref.amount) }}</strong>
          </div>
          <p class="ref-customer">
            Khách: <strong>{{ ref.booking?.user?.name || 'Khách hàng' }}</strong> ({{ ref.booking?.user?.phone || '0901234567' }})
          </p>
          <p class="ref-reason">Lý do: {{ ref.note || 'Khách yêu cầu huỷ lịch' }}</p>
        </div>

        <div class="ref-actions-col">
          <button class="btn-approve" @click="emit('approve', ref.id)">
            ✓ Duyệt Hoàn Tiền
          </button>
          <button class="btn-reject" @click="emit('reject', ref.id)">
            ✕ Từ Chối
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.refunds-panel-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border-radius: 16px;
  background: var(--surface);
  border: 1px solid var(--outline);
}

.refunds-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.panel-headline {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.panel-sub {
  margin: 4px 0 0;
  font-size: 12px;
  color: var(--muted);
}

.refund-count-badge {
  padding: 4px 12px;
  border-radius: 20px;
  background: rgba(236, 72, 153, 0.1);
  color: var(--pink-dark, #db2777);
  font-weight: 700;
  font-size: 12px;
}

.empty-refunds-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 40px 20px;
  text-align: center;
  color: var(--muted);
  font-size: 13px;
}

.empty-refunds-box span {
  font-size: 32px;
  margin-bottom: 8px;
}

.refunds-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.refund-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  flex-wrap: wrap;
  gap: 12px;
}

.ref-info-col {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.ref-title-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.ref-booking-code {
  font-weight: 700;
  font-size: 14px;
}

.ref-amount {
  font-size: 14px;
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.ref-customer,
.ref-reason {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
}

.ref-actions-col {
  display: flex;
  gap: 8px;
}

.btn-approve {
  padding: 8px 14px;
  border-radius: 8px;
  border: 0;
  background: #10b981;
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.btn-reject {
  padding: 8px 14px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: #ef4444;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}
</style>

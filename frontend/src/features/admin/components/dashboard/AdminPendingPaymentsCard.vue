<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { ReviewPayment } from '../../composables/useAdminDashboard'

defineProps<{
  payments: ReviewPayment[]
}>()

const emit = defineEmits<{
  (e: 'verify', payment: ReviewPayment): void
}>()

const { t } = useI18n()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="pending-payments-card">
    <div class="card-header">
      <div class="title-with-icon">
        <span class="material-symbols-outlined card-title-icon">credit_card</span>
        <h3 class="title">{{ t('admin.dashboard.pendingManualPayments', { count: payments.length }) }}</h3>
      </div>
      <span class="sub">{{ t('admin.dashboard.reconcileBankTransfers') }}</span>
    </div>

    <div v-if="payments.length === 0" class="empty-pay">
      {{ t('admin.dashboard.noPendingPayments') }}
    </div>

    <div v-else class="payments-list">
      <div v-for="p in payments" :key="p.id" class="pay-item">
        <div class="p-info">
          <strong class="font-mono text-pink">{{ formatMoney(p.amount) }}</strong>
          <span class="transfer-code font-mono">{{ t('admin.dashboard.transferContent', { code: p.transfer_code }) }}</span>
          <span class="booking-ref">{{ t('admin.dashboard.bookingRef', { code: p.booking?.code || '', customer: p.booking?.user?.name || t('admin.dashboard.walkInCustomer') }) }}</span>
        </div>

        <button type="button" class="btn-verify" @click="emit('verify', p)">
          <span class="material-symbols-outlined icon-16">check_circle</span>
          <span>{{ t('admin.dashboard.verifyPayment') }}</span>
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
  flex-wrap: wrap;
  gap: 8px;
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-title-icon {
  font-size: 20px;
  color: var(--pink-dark, #db2777);
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
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 8px;
  border: 0;
  background: #10b981;
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: opacity 0.15s;
}

.btn-verify:hover {
  opacity: 0.9;
}

.icon-16 {
  font-size: 16px;
}
</style>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { ReviewPayment } from '../../composables/useAdminDashboard'

defineProps<{
  payments: ReviewPayment[]
  loading?: boolean
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
        <h3 class="title">
          {{ t('admin.dashboard.pendingManualPayments', { count: payments.length }) }}
        </h3>
      </div>
      <span class="sub">{{ t('admin.dashboard.reconcileBankTransfers') }}</span>
    </div>

    <!-- Skeleton Loading State -->
    <div v-if="loading" class="payments-list">
      <div v-for="i in 3" :key="i" class="pay-item skeleton-pay">
        <div class="p-info">
          <div class="skeleton-item skeleton-amount"></div>
          <div class="skeleton-item skeleton-code"></div>
          <div class="skeleton-item skeleton-ref"></div>
        </div>
        <div class="skeleton-item skeleton-btn"></div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="payments.length === 0" class="empty-pay">
      {{ t('admin.dashboard.noPendingPayments') }}
    </div>

    <!-- Real List -->
    <div v-else class="payments-list">
      <div v-for="p in payments" :key="p.id" class="pay-item">
        <div class="p-info">
          <strong class="font-mono text-pink">{{ formatMoney(p.amount) }}</strong>
          <span class="transfer-code font-mono">{{
            t('admin.dashboard.transferContent', { code: p.transfer_code })
          }}</span>
          <span class="booking-ref">{{
            t('admin.dashboard.bookingRef', {
              code: p.booking?.code || '',
              customer: p.booking?.user?.name || t('admin.dashboard.walkInCustomer'),
            })
          }}</span>
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
  flex-direction: column;
  gap: 4px;
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-title-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
}

.title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.sub {
  font-size: 12px;
  color: var(--muted);
}

.empty-pay {
  padding: 30px 20px;
  text-align: center;
  color: var(--muted);
  font-size: 13px;
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
  gap: 12px;
}

.p-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.transfer-code {
  font-size: 12px;
  font-weight: 600;
}

.booking-ref {
  font-size: 11px;
  color: var(--muted);
}

.btn-verify {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: 6px;
  border: 0;
  background: #10b981;
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}

.icon-16 {
  font-size: 16px;
}

/* Skeleton Shimmer */
.skeleton-pay {
  pointer-events: none;
}

.skeleton-item {
  position: relative;
  overflow: hidden;
  background-color: var(--soft, #eff1f4);
  border-radius: 6px;
}

.skeleton-item::after {
  content: '';
  position: absolute;
  inset: 0;
  transform: translateX(-100%);
  background: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0%,
    rgba(255, 209, 220, 0.45) 45%,
    rgba(255, 255, 255, 0.8) 55%,
    rgba(255, 255, 255, 0) 100%
  );
  animation: shimmer-wave 1.6s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

:global(html.dark) .skeleton-item {
  background-color: var(--soft, #2b2e36);
}

:global(html.dark) .skeleton-item::after {
  background: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0%,
    rgba(120, 85, 94, 0.35) 45%,
    rgba(255, 255, 255, 0.1) 55%,
    rgba(255, 255, 255, 0) 100%
  );
}

.skeleton-amount {
  width: 90px;
  height: 16px;
}

.skeleton-code {
  width: 130px;
  height: 12px;
  margin-top: 3px;
}

.skeleton-ref {
  width: 160px;
  height: 10px;
  margin-top: 3px;
}

.skeleton-btn {
  width: 80px;
  height: 28px;
  border-radius: 6px;
}

@keyframes shimmer-wave {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(100%);
  }
}
</style>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import type { Refund } from '@/features/booking/types/booking'

defineProps<{
  refunds: Refund[]
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'approve', refundId: number): void
  (e: 'reject', refundId: number): void
}>()

const { t } = useI18n()
</script>

<template>
  <div class="refunds-panel-container">
    <div class="refunds-header">
      <div>
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">currency_exchange</span>
          <h3 class="panel-headline">{{ t('admin.manage.refundsPanelTitle') }}</h3>
        </div>
        <p class="panel-sub">{{ t('admin.manage.refundsPanelSub') }}</p>
      </div>
      <span class="refund-count-badge font-mono">{{ t('admin.manage.refundsCount', { count: refunds.length }) }}</span>
    </div>

    <!-- Skeleton Loading State -->
    <div v-if="loading" class="refunds-list">
      <div v-for="i in 4" :key="i" class="refund-card skeleton-card">
        <div class="ref-info-col">
          <div class="ref-title-row">
            <div class="skeleton-item skeleton-code"></div>
            <div class="skeleton-item skeleton-amt"></div>
          </div>
          <div class="skeleton-item skeleton-text"></div>
          <div class="skeleton-item skeleton-text short"></div>
        </div>

        <div class="ref-actions-col">
          <div class="skeleton-item skeleton-action-btn"></div>
          <div class="skeleton-item skeleton-action-btn"></div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="refunds.length === 0" class="empty-refunds-box">
      <span class="material-symbols-outlined empty-icon">check_circle</span>
      <p>{{ t('admin.manage.noPendingRefunds') }}</p>
    </div>

    <!-- Real Refunds List -->
    <div v-else class="refunds-list">
      <div v-for="ref in refunds" :key="ref.id" class="refund-card">
        <div class="ref-info-col">
          <div class="ref-title-row">
            <span class="ref-booking-code font-mono">{{ ref.booking?.code }}</span>
            <strong class="ref-amount text-pink font-mono">{{ formatCurrency(ref.amount) }}</strong>
          </div>
          <p class="ref-customer">
            {{ t('admin.manage.customerLabel', { name: ref.booking?.user?.name || t('admin.dashboard.walkInCustomer'), phone: ref.booking?.user?.phone || 'N/A' }) }}
          </p>
          <p class="ref-reason">{{ t('admin.manage.reasonLabel', { reason: ref.note || t('admin.manage.defaultCancelReason') }) }}</p>
        </div>

        <div class="ref-actions-col">
          <button type="button" class="btn-approve" @click="emit('approve', ref.id)">
            <span class="material-symbols-outlined icon-16">check</span>
            <span>{{ t('admin.manage.approveRefundBtn') }}</span>
          </button>
          <button type="button" class="btn-reject" @click="emit('reject', ref.id)">
            <span class="material-symbols-outlined icon-16">close</span>
            <span>{{ t('admin.manage.rejectRefundBtn') }}</span>
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

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-title-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
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

.empty-icon {
  font-size: 36px;
  color: #10b981;
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
  display: inline-flex;
  align-items: center;
  gap: 4px;
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
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 8px 14px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: #ef4444;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.icon-16 {
  font-size: 16px;
}

/* Shimmer Skeleton */
.skeleton-card {
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

.skeleton-code {
  width: 90px;
  height: 16px;
}

.skeleton-amt {
  width: 80px;
  height: 16px;
}

.skeleton-text {
  width: 220px;
  height: 12px;
  margin-top: 2px;
}

.skeleton-text.short {
  width: 160px;
}

.skeleton-action-btn {
  width: 90px;
  height: 32px;
  border-radius: 8px;
}

@keyframes shimmer-wave {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}
</style>

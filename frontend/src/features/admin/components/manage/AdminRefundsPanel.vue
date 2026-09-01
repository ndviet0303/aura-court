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

    <div v-if="refunds.length === 0" class="empty-refunds-box">
      <span class="material-symbols-outlined empty-icon">check_circle</span>
      <p>{{ t('admin.manage.noPendingRefunds') }}</p>
    </div>

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
</style>

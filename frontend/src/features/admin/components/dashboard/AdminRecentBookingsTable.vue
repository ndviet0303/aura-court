<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText, formatSlotRange, formatCurrency } from '@/i18n'
import type { AdminBooking } from '../../composables/useAdminDashboard'

defineProps<{
  bookings: AdminBooking[]
  search: string
  status: string
}>()

const emit = defineEmits<{
  (e: 'update:search', val: string): void
  (e: 'update:status', val: string): void
}>()

const { t } = useI18n()

function getStatusLabel(status?: string): string {
  switch (status) {
    case 'confirmed':
      return t('admin.dashboard.statusConfirmed')
    case 'pending':
    case 'pending_payment':
      return t('admin.dashboard.statusPending')
    case 'cancelled':
      return t('admin.dashboard.statusCancelled')
    case 'expired':
      return t('admin.dashboard.statusExpired')
    case 'no_show':
      return t('admin.dashboard.statusNoShow')
    case 'refund_pending':
      return t('admin.dashboard.statusRefundPending')
    case 'refunded':
      return t('admin.dashboard.statusRefunded')
    default:
      return status || t('admin.dashboard.statusPending')
  }
}
</script>

<template>
  <div class="recent-bookings-card">
    <div class="card-header">
      <div class="header-titles">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">receipt_long</span>
          <h3 class="title">{{ t('admin.dashboard.recentBookings') }}</h3>
        </div>
        <p class="sub">{{ t('admin.dashboard.recentBookingsSub') }}</p>
      </div>

      <div class="filters-row">
        <input
          :value="search"
          type="text"
          class="search-input"
          :placeholder="t('admin.dashboard.searchBookingPlaceholder')"
          @input="emit('update:search', ($event.target as HTMLInputElement).value)"
        />

        <select
          :value="status"
          class="status-select"
          @change="emit('update:status', ($event.target as HTMLSelectElement).value)"
        >
          <option value="">{{ t('admin.dashboard.allStatuses') }}</option>
          <option value="confirmed">{{ t('admin.dashboard.statusConfirmed') }}</option>
          <option value="pending_payment">{{ t('admin.dashboard.statusPending') }}</option>
          <option value="cancelled">{{ t('admin.dashboard.statusCancelled') }}</option>
          <option value="expired">{{ t('admin.dashboard.statusExpired') }}</option>
          <option value="no_show">{{ t('admin.dashboard.statusNoShow') }}</option>
          <option value="refund_pending">{{ t('admin.dashboard.statusRefundPending') }}</option>
        </select>
      </div>
    </div>

    <div class="table-responsive">
      <div class="bookings-table">
        <div class="table-hdr">
          <span>{{ t('admin.dashboard.bookingCode') }}</span>
          <span>{{ t('admin.dashboard.customer') }}</span>
          <span>{{ t('admin.dashboard.courtAndSlot') }}</span>
          <span>{{ t('admin.dashboard.totalAmount') }}</span>
          <span>{{ t('admin.dashboard.status') }}</span>
        </div>

        <div class="table-body">
          <div v-for="b in bookings" :key="b.id" class="booking-row">
            <strong class="font-mono text-pink">{{ b.code }}</strong>
            <div class="user-col">
              <strong>{{ b.user?.name || t('admin.dashboard.walkInCustomer') }}</strong>
              <span class="email-sub">{{ b.user?.email }}</span>
            </div>
            <div class="court-col">
              <strong>{{ resolveLocalizedText(b.court?.name) }}</strong>
              <span class="slot-sub font-mono">
                {{ formatSlotRange(b.slots?.[0]?.starts_at, b.slots?.[0]?.ends_at) }}
              </span>
            </div>
            <strong class="font-mono">{{ formatCurrency(b.total_amount) }}</strong>
            <span class="status-pill" :class="`status-${b.status}`">
              ● {{ getStatusLabel(b.status) }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.recent-bookings-card {
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
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
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
  margin: 4px 0 0;
  font-size: 12px;
  color: var(--muted);
}

.filters-row {
  display: flex;
  gap: 10px;
}

.search-input, .status-select {
  padding: 8px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  font-size: 12px;
}

.bookings-table {
  display: flex;
  flex-direction: column;
  border-radius: 12px;
  border: 1px solid var(--outline);
  overflow: hidden;
  min-width: 700px;
}

.table-hdr {
  display: grid;
  grid-template-columns: 1.2fr 1.8fr 2fr 1.2fr 1.2fr;
  padding: 12px 16px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
}

.booking-row {
  display: grid;
  grid-template-columns: 1.2fr 1.8fr 2fr 1.2fr 1.2fr;
  padding: 12px 16px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 13px;
}

.booking-row:last-child {
  border-bottom: 0;
}

.text-pink { color: var(--pink-dark, #db2777); }

.user-col, .court-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.email-sub, .slot-sub {
  font-size: 11px;
  color: var(--muted);
}

.status-pill {
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
  white-space: nowrap;
}

.status-confirmed, .status-paid { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.status-pending, .status-pending_payment { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.status-cancelled, .status-expired { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.status-no_show { background: rgba(107, 114, 128, 0.15); color: #6b7280; }
.status-refund_pending, .status-refunded { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
</style>

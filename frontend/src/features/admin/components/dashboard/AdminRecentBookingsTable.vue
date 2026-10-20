<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import SelectDropdown from '@/shared/components/forms/SelectDropdown.vue'
import { resolveLocalizedText, formatSlotRange, formatCurrency } from '@/i18n'
import {
  RESERVATION_STATUSES,
  reservationCode,
  reservationStatus,
  type AdminReservation,
} from '../../composables/useAdminDashboard'

defineProps<{
  bookings: AdminReservation[]
  search: string
  status: string
  loading?: boolean
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
    case 'held':
    case 'pending_payment':
      return t('admin.dashboard.statusPending')
    case 'in_use':
      return t('admin.dashboard.statusInUse')
    case 'completed':
      return t('admin.dashboard.statusCompleted')
    case 'disputed':
      return t('admin.dashboard.statusDisputed')
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

const statusOptions = computed(() => [
  { value: '', label: t('admin.dashboard.allStatuses') },
  ...RESERVATION_STATUSES.map((s) => ({ value: s, label: getStatusLabel(s) })),
])
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

        <SelectDropdown
          :model-value="status"
          :options="statusOptions"
          variant="bordered"
          align="right"
          class="status-select"
          :aria-label="t('admin.dashboard.status')"
          @update:model-value="(v) => emit('update:status', String(v))"
        />
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

        <!-- Skeleton Rows Loading -->
        <div v-if="loading" class="table-body">
          <div v-for="i in 5" :key="i" class="booking-row skeleton-row">
            <div class="skeleton-item skeleton-code"></div>
            <div class="user-col">
              <div class="skeleton-item skeleton-name"></div>
              <div class="skeleton-item skeleton-email"></div>
            </div>
            <div class="court-col">
              <div class="skeleton-item skeleton-court"></div>
              <div class="skeleton-item skeleton-slot"></div>
            </div>
            <div class="skeleton-item skeleton-amt"></div>
            <div class="skeleton-item skeleton-status"></div>
          </div>
        </div>

        <!-- Real Rows -->
        <div v-else-if="bookings.length > 0" class="table-body">
          <div v-for="b in bookings" :key="b.id" class="booking-row">
            <strong class="font-mono text-pink">{{ reservationCode(b) }}</strong>
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
            <span class="status-pill" :class="reservationStatus(b)">
              {{ getStatusLabel(reservationStatus(b)) }}
            </span>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="empty-bookings">
          <span class="material-symbols-outlined empty-icon">inbox</span>
          <p>{{ t('admin.dashboard.noBookings') }}</p>
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
  align-items: center;
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
  align-items: center;
}

.search-input {
  padding: 8px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  width: 180px;
}

.status-select {
  flex: 0 0 180px;
}

.table-responsive {
  overflow-x: auto;
}

.bookings-table {
  min-width: 600px;
  display: flex;
  flex-direction: column;
}

.table-hdr {
  display: grid;
  grid-template-columns: 1.2fr 1.5fr 1.5fr 1.2fr 1fr;
  padding: 10px 14px;
  border-radius: 8px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.table-body {
  display: flex;
  flex-direction: column;
}

.booking-row {
  display: grid;
  grid-template-columns: 1.2fr 1.5fr 1.5fr 1.2fr 1fr;
  align-items: center;
  padding: 12px 14px;
  border-bottom: 1px solid var(--outline);
  font-size: 13px;
}

.booking-row:last-child {
  border-bottom: 0;
}

.user-col,
.court-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.email-sub,
.slot-sub {
  font-size: 11px;
  color: var(--muted);
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.status-pill {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
  text-align: center;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.06));
}

.status-pill.confirmed {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
}

.status-pill.held,
.status-pill.pending_payment {
  background: rgba(245, 158, 11, 0.12);
  color: #f59e0b;
}

.status-pill.cancelled,
.status-pill.expired {
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
}

.empty-bookings {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 40px 20px;
  color: var(--muted);
  font-size: 13px;
}

.empty-icon {
  font-size: 36px;
  margin-bottom: 8px;
}

/* Skeleton Shimmer */
.skeleton-row {
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
  width: 80px;
  height: 14px;
}

.skeleton-name {
  width: 100px;
  height: 14px;
}

.skeleton-email {
  width: 130px;
  height: 10px;
  margin-top: 3px;
}

.skeleton-court {
  width: 90px;
  height: 14px;
}

.skeleton-slot {
  width: 110px;
  height: 10px;
  margin-top: 3px;
}

.skeleton-amt {
  width: 70px;
  height: 14px;
}

.skeleton-status {
  width: 70px;
  height: 20px;
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

<script setup lang="ts">
import { useRouter } from 'vue-router'
import { AuraButton, SkeletonLoader } from '@/shared/components'
import type { RecurringSeries } from '../../types/recurring'

defineProps<{
  seriesList: RecurringSeries[]
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'create-new'): void
}>()

const router = useRouter()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}

function formatDate(d: string): string {
  if (!d) return ''
  return d.slice(0, 10)
}

function formatFrequency(freq: string): string {
  switch (freq) {
    case 'weekly':
      return 'Hàng tuần'
    case 'biweekly':
      return '2 tuần/lần'
    case 'monthly':
      return 'Hàng tháng'
    default:
      return freq
  }
}

function openInvoiceDetail(inv: { id: number | string; uuid?: string } | number | string) {
  const key = typeof inv === 'object' ? inv.uuid || inv.id : inv
  router.push(`/recurring-booking/cycles/${key}`)
}
</script>

<template>
  <div class="recurring-manager-container">
    <div class="manager-header">
      <div class="header-icon-box">
        <span class="material-symbols-outlined icon-heading">calendar_month</span>
      </div>
      <div>
        <h3 class="manager-title">
          Quản Lý Hợp Đồng Đặt Sân Cố Định
          <span v-if="seriesList.length > 0" class="count-badge font-mono">{{
            seriesList.length
          }}</span>
        </h3>
        <p class="manager-sub">
          Kiểm tra lịch đặt sân hàng tuần và danh sách hóa đơn thanh toán định kỳ.
        </p>
      </div>
    </div>

    <!-- Skeleton Loading State -->
    <div v-if="loading" class="series-items-list">
      <div v-for="i in 3" :key="i" class="series-skeleton-card">
        <div class="skeleton-top">
          <SkeletonLoader type="rect" width="140px" height="18px" rounded="6px" />
          <SkeletonLoader type="rect" width="100px" height="22px" rounded="999px" />
        </div>
        <SkeletonLoader type="title" width="60%" height="20px" />
        <div class="skeleton-grid">
          <SkeletonLoader type="rect" width="100%" height="32px" rounded="8px" />
          <SkeletonLoader type="rect" width="100%" height="32px" rounded="8px" />
        </div>
        <SkeletonLoader type="rect" width="100%" height="60px" rounded="10px" />
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="seriesList.length === 0" class="empty-state-card">
      <div class="empty-icon-wrap">
        <span class="material-symbols-outlined empty-icon">event_busy</span>
      </div>
      <h4 class="empty-title">Chưa Có Hợp Đồng Đặt Sân Nào</h4>
      <p class="empty-desc">
        Bạn chưa đăng ký lịch đặt sân cố định nào. Hãy thiết lập hợp đồng để giữ khung giờ chơi ưa
        thích của bạn!
      </p>
      <AuraButton variant="primary" size="md" icon="add_circle" @click="emit('create-new')">
        <span>Tạo Lịch Cố Định Ngay</span>
      </AuraButton>
    </div>

    <!-- Active Series Cards -->
    <div v-else class="series-items-list">
      <div v-for="s in seriesList" :key="s.id" class="series-card">
        <div class="series-card-top">
          <div class="series-badge-group">
            <span class="series-id-pill font-mono">HỢP ĐỒNG #{{ s.id }}</span>
            <span v-if="s.frequency" class="freq-pill">{{ formatFrequency(s.frequency) }}</span>
          </div>

          <div class="status-indicator">
            <span
              class="status-pill"
              :class="{
                'status-active': s.status === 'active' || s.status === 'confirmed',
                'status-paused': s.status === 'paused',
                'status-cancelled': s.status === 'cancelled',
                'status-delinquent': s.status === 'delinquent',
              }"
            >
              <span class="status-dot"></span>
              {{
                s.status === 'active' || s.status === 'confirmed'
                  ? 'Đang hoạt động'
                  : s.status === 'paused'
                    ? 'Tạm dừng'
                    : s.status === 'delinquent'
                      ? 'Quá hạn'
                      : 'Đã hủy'
              }}
            </span>
          </div>
        </div>

        <!-- Court and Venue Details -->
        <div class="series-court-row">
          <div class="court-icon-box">
            <span class="material-symbols-outlined court-symbol">sports_tennis</span>
          </div>
          <div class="court-details">
            <h4 class="court-heading">
              {{ s.court?.name || `Sân #${s.court_id}` }}
            </h4>
            <p v-if="s.court?.venue" class="venue-line">
              <span class="material-symbols-outlined loc-pin">location_on</span>
              {{ s.court.venue.name }} — {{ s.court.venue.address }}
            </p>
          </div>
        </div>

        <!-- Schedule & Financial Metrics Grid -->
        <div class="series-metrics-grid">
          <div class="metric-item">
            <span class="metric-label">Khung giờ</span>
            <span class="metric-value font-mono">
              {{ s.starts_at ? s.starts_at.slice(11, 16) : '18:00' }} -
              {{ s.ends_at ? s.ends_at.slice(11, 16) : '20:00' }}
            </span>
          </div>

          <div class="metric-item">
            <span class="metric-label">Thời hạn áp dụng</span>
            <span class="metric-value">
              {{ formatDate(s.starts_at) }} ➔ {{ formatDate(s.ends_at) }}
            </span>
          </div>

          <div class="metric-item">
            <span class="metric-label">Tổng số buổi</span>
            <span class="metric-value font-mono"
              >{{ s.occurrence_count || s.occurrences?.length || 0 }} buổi</span
            >
          </div>

          <div class="metric-item">
            <span class="metric-label">Tổng giá trị</span>
            <span class="metric-value-highlight font-mono">{{ formatMoney(s.total_amount) }}</span>
          </div>
        </div>

        <!-- Monthly Invoices Box -->
        <div v-if="s.invoices && s.invoices.length > 0" class="invoices-section">
          <div class="invoices-head">
            <span class="material-symbols-outlined inv-icon">receipt_long</span>
            <span class="invoices-title">Hóa Đơn Chu Kỳ Tháng:</span>
          </div>

          <div class="invoices-list">
            <div v-for="inv in s.invoices" :key="inv.id" class="invoice-item-card">
              <div class="inv-info">
                <span class="inv-period">
                  Kỳ: {{ formatDate(inv.period_start) }} ➔ {{ formatDate(inv.period_end) }}
                </span>
                <span class="inv-amount font-mono">{{ formatMoney(inv.amount) }}</span>
              </div>

              <div class="inv-actions">
                <span
                  class="inv-status-tag"
                  :class="{
                    'status-paid': inv.status === 'paid',
                    'status-pending': inv.status === 'pending',
                    'status-overdue': inv.status === 'overdue',
                  }"
                >
                  ●
                  {{
                    inv.status === 'paid'
                      ? 'Đã đóng'
                      : inv.status === 'overdue'
                        ? 'Quá hạn'
                        : 'Chờ thanh toán'
                  }}
                </span>

                <AuraButton
                  variant="secondary"
                  size="sm"
                  icon="visibility"
                  @click="openInvoiceDetail(inv)"
                >
                  <span>Chi tiết & Thanh toán</span>
                </AuraButton>
              </div>
            </div>
          </div>
        </div>

        <!-- Occurrences preview -->
        <div v-if="s.occurrences && s.occurrences.length > 0" class="occurrences-preview">
          <div class="occ-head">
            <span class="material-symbols-outlined occ-icon">schedule</span>
            <span class="occ-title">Các buổi sắp diễn ra:</span>
          </div>
          <div class="occ-chips-row">
            <span v-for="occ in s.occurrences.slice(0, 5)" :key="occ.id" class="occ-pill">
              Buổi {{ occ.sequence }}: {{ formatDate(occ.starts_at) }} ({{
                occ.starts_at.slice(11, 16)
              }})
            </span>
            <span v-if="s.occurrences.length > 5" class="occ-more">
              +{{ s.occurrences.length - 5 }} buổi nữa
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.recurring-manager-container {
  display: flex;
  flex-direction: column;
  gap: 20px;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
  padding: 24px;
}

.manager-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--outline);
}

.header-icon-box {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: rgba(16, 185, 129, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.icon-heading {
  font-size: 24px;
  color: #10b981;
}

.manager-title {
  margin: 0 0 2px;
  font-size: 17px;
  font-weight: 700;
  color: var(--ink);
  display: flex;
  align-items: center;
  gap: 8px;
}

.count-badge {
  font-size: 12px;
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.05));
  border: 1px solid var(--outline);
  color: var(--pink-dark, #db2777);
}

.manager-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.empty-state-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 48px 24px;
  border-radius: 12px;
  border: 1px dashed var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.01));
  gap: 12px;
}

.empty-icon-wrap {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  display: flex;
  align-items: center;
  justify-content: center;
}

.empty-icon {
  font-size: 32px;
  color: var(--muted);
}

.empty-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: var(--ink);
}

.empty-desc {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
  max-width: 420px;
  line-height: 1.5;
}

.series-items-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.series-skeleton-card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 18px;
  border-radius: 12px;
  border: 1px solid var(--outline);
}

.skeleton-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.skeleton-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}

.series-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  transition: all 0.2s;
}

.series-card:hover {
  border-color: var(--pink-dark, #db2777);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
}

.series-card-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.series-badge-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.series-id-pill {
  font-size: 12px;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 6px;
  background: var(--surface);
  border: 1px solid var(--outline);
  color: var(--ink);
}

.freq-pill {
  font-size: 11px;
  font-weight: 600;
  padding: 4px 8px;
  border-radius: 6px;
  background: rgba(219, 39, 119, 0.1);
  color: var(--pink-dark, #db2777);
}

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
}

.status-active {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.status-paused {
  background: rgba(245, 158, 11, 0.1);
  color: #f59e0b;
}

.status-cancelled {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.status-delinquent {
  background: rgba(239, 68, 68, 0.15);
  color: #dc2626;
}

.series-court-row {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.court-icon-box {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: var(--surface);
  border: 1px solid var(--outline);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.court-symbol {
  font-size: 20px;
  color: var(--pink-dark, #db2777);
}

.court-details {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.court-heading {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: var(--ink);
}

.sport-tag {
  font-size: 12px;
  font-weight: 500;
  color: var(--muted);
}

.venue-line {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
  display: flex;
  align-items: center;
  gap: 4px;
}

.loc-pin {
  font-size: 14px;
}

.series-metrics-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
  padding: 12px;
  border-radius: 10px;
  background: var(--surface);
  border: 1px solid var(--outline);
}

@media (max-width: 768px) {
  .series-metrics-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

.metric-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.metric-label {
  font-size: 11px;
  color: var(--muted);
}

.metric-value {
  font-size: 12px;
  font-weight: 600;
  color: var(--ink);
}

.metric-value-highlight {
  font-size: 13px;
  font-weight: 700;
  color: var(--pink-dark, #db2777);
}

.invoices-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px;
  border-radius: 10px;
  background: var(--surface);
  border: 1px solid var(--outline);
}

.invoices-head {
  display: flex;
  align-items: center;
  gap: 6px;
}

.inv-icon {
  font-size: 18px;
  color: #3b82f6;
}

.invoices-title {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
}

.invoices-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.invoice-item-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 8px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.inv-info {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
}

.inv-period {
  font-size: 12px;
  font-weight: 600;
  color: var(--ink);
}

.inv-amount {
  font-size: 13px;
  font-weight: 700;
  color: var(--pink-dark, #db2777);
}

.inv-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.inv-status-tag {
  font-size: 11px;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 999px;
}

.status-paid {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.status-pending {
  background: rgba(245, 158, 11, 0.1);
  color: #f59e0b;
}

.status-overdue {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.occurrences-preview {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.occ-head {
  display: flex;
  align-items: center;
  gap: 6px;
}

.occ-icon {
  font-size: 16px;
  color: var(--muted);
}

.occ-title {
  font-size: 12px;
  font-weight: 600;
  color: var(--muted);
}

.occ-chips-row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.occ-pill {
  padding: 4px 10px;
  border-radius: 6px;
  background: var(--surface);
  border: 1px solid var(--outline);
  font-size: 11px;
  font-weight: 600;
  color: var(--ink);
}

.occ-more {
  padding: 4px 8px;
  font-size: 11px;
  color: var(--muted);
}
</style>

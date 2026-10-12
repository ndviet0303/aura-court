<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { AuraButton, SkeletonLoader } from '@/shared/components'
import type { RecurringInvoice, InvoiceStatus } from '../../types/recurring'

const props = defineProps<{
  invoices: RecurringInvoice[]
  loading?: boolean
}>()

const router = useRouter()
const filterStatus = ref<'all' | InvoiceStatus>('all')

const filteredInvoices = computed(() => {
  if (filterStatus.value === 'all') return props.invoices
  return props.invoices.filter((inv) => inv.status === filterStatus.value)
})

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}

function formatDate(d: string): string {
  if (!d) return ''
  return d.slice(0, 10)
}

function openInvoiceDetail(inv: RecurringInvoice) {
  const key = inv.uuid || inv.id
  router.push(`/recurring-booking/cycles/${key}`)
}
</script>

<template>
  <div class="invoices-manager-container">
    <div class="invoices-header">
      <div class="header-icon-box">
        <span class="material-symbols-outlined icon-heading">receipt_long</span>
      </div>
      <div class="header-titles">
        <h3 class="invoices-title">
          Danh Sách Hóa Đơn Chu Kỳ Tháng
          <span v-if="invoices.length > 0" class="count-badge font-mono">{{
            invoices.length
          }}</span>
        </h3>
        <p class="invoices-sub">
          Theo dõi nghĩa vụ từng kỳ và chỉ dùng hướng dẫn chuyển khoản được xác minh của chủ sân.
        </p>
      </div>
    </div>

    <!-- Filters Row -->
    <div class="filters-row">
      <div class="status-filter-tabs">
        <button
          type="button"
          class="filter-pill"
          :class="{ active: filterStatus === 'all' }"
          @click="filterStatus = 'all'"
        >
          Tất cả ({{ invoices.length }})
        </button>
        <button
          type="button"
          class="filter-pill"
          :class="{ active: filterStatus === 'pending' }"
          @click="filterStatus = 'pending'"
        >
          Chờ thanh toán ({{ invoices.filter((i) => i.status === 'pending').length }})
        </button>
        <button
          type="button"
          class="filter-pill"
          :class="{ active: filterStatus === 'guaranteed' }"
          @click="filterStatus = 'guaranteed'"
        >
          Được bảo lãnh ({{ invoices.filter((i) => i.status === 'guaranteed').length }})
        </button>
        <button
          type="button"
          class="filter-pill"
          :class="{ active: filterStatus === 'zero_payable' }"
          @click="filterStatus = 'zero_payable'"
        >
          Không cần thanh toán ({{ invoices.filter((i) => i.status === 'zero_payable').length }})
        </button>
        <button
          type="button"
          class="filter-pill"
          :class="{ active: filterStatus === 'paid' }"
          @click="filterStatus = 'paid'"
        >
          Đã thanh toán ({{ invoices.filter((i) => i.status === 'paid').length }})
        </button>
        <button
          type="button"
          class="filter-pill"
          :class="{ active: filterStatus === 'overdue' }"
          @click="filterStatus = 'overdue'"
        >
          Quá hạn ({{ invoices.filter((i) => i.status === 'overdue').length }})
        </button>
      </div>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading" class="invoices-cards-list">
      <div v-for="i in 3" :key="i" class="inv-skeleton-card">
        <SkeletonLoader type="rect" width="120px" height="18px" rounded="6px" />
        <SkeletonLoader type="rect" width="100%" height="24px" rounded="6px" />
        <SkeletonLoader type="rect" width="100%" height="40px" rounded="8px" />
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="filteredInvoices.length === 0" class="empty-state-card">
      <div class="empty-icon-wrap">
        <span class="material-symbols-outlined empty-icon">receipt</span>
      </div>
      <h4 class="empty-title">Không Tìm Thấy Hóa Đơn Nào</h4>
      <p class="empty-desc">
        {{
          filterStatus === 'all'
            ? 'Chưa có hóa đơn chu kỳ nào được phát hành.'
            : 'Không có hóa đơn nào phù hợp với bộ lọc hiện tại.'
        }}
      </p>
    </div>

    <!-- Invoices List -->
    <div v-else class="invoices-cards-list">
      <div v-for="inv in filteredInvoices" :key="inv.id" class="invoice-card">
        <div class="inv-card-top">
          <div class="inv-badge-group">
            <span class="inv-id font-mono">HÓA ĐƠN #{{ inv.id }}</span>
            <span class="series-ref font-mono">Hợp đồng #{{ inv.recurring_series_id }}</span>
          </div>

          <span
            class="status-pill"
            :class="{
              'status-paid': inv.status === 'paid',
              'status-pending':
                inv.status === 'pending' ||
                inv.status === 'guaranteed' ||
                inv.status === 'zero_payable',
              'status-overdue': inv.status === 'overdue',
            }"
          >
            ●
            {{
              inv.status === 'paid'
                ? 'Đã thanh toán'
                : inv.status === 'zero_payable'
                  ? 'Không cần thanh toán (0 ₫)'
                  : inv.status === 'guaranteed'
                    ? 'Được bảo lãnh (chưa thanh toán)'
                    : inv.status === 'overdue'
                      ? 'Quá hạn'
                      : inv.status === 'cancelled'
                        ? 'Đã huỷ'
                        : 'Chờ thanh toán'
            }}
          </span>
        </div>

        <div class="inv-card-content">
          <div class="inv-venue-info" v-if="inv.series?.court">
            <span class="material-symbols-outlined court-icon">sports_tennis</span>
            <div class="venue-texts">
              <span class="court-title">{{ inv.series.court.name }}</span>
              <span v-if="inv.series.court.venue" class="venue-sub">{{
                inv.series.court.venue.name
              }}</span>
            </div>
          </div>

          <div class="inv-details-grid">
            <div class="inv-col">
              <span class="col-lbl">Kỳ thanh toán</span>
              <span class="col-val"
                >{{ formatDate(inv.period_start) }} ➔ {{ formatDate(inv.period_end) }}</span
              >
            </div>

            <div class="inv-col">
              <span class="col-lbl">Hạn thanh toán</span>
              <span class="col-val">{{
                inv.status === 'zero_payable' ? 'Không áp dụng' : formatDate(inv.due_at)
              }}</span>
            </div>

            <div class="inv-col">
              <span class="col-lbl">Tổng tiền kỳ này</span>
              <span class="col-val-amount font-mono">{{ formatMoney(inv.amount) }}</span>
            </div>
          </div>
        </div>

        <div class="inv-card-actions">
          <AuraButton variant="primary" size="sm" icon="payments" @click="openInvoiceDetail(inv)">
            <span>{{
              inv.status === 'paid' ? 'Xem Chi Tiết Lịch Buổi' : 'Xem Trạng Thái Thanh Toán'
            }}</span>
          </AuraButton>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.invoices-manager-container {
  display: flex;
  flex-direction: column;
  gap: 20px;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
  padding: 24px;
}

.invoices-header {
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
  background: rgba(59, 130, 246, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.icon-heading {
  font-size: 24px;
  color: #3b82f6;
}

.invoices-title {
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
  color: #3b82f6;
}

.invoices-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.filters-row {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding-bottom: 4px;
}

.status-filter-tabs {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.filter-pill {
  padding: 6px 14px;
  border-radius: 999px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--muted);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.filter-pill:hover {
  color: var(--ink);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
}

.filter-pill.active {
  background: #3b82f6;
  border-color: #3b82f6;
  color: #fff;
}

.invoices-cards-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.inv-skeleton-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 16px;
  border-radius: 12px;
  border: 1px solid var(--outline);
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
  gap: 10px;
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
  font-size: 30px;
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
  max-width: 400px;
}

.invoice-card {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 18px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  transition: all 0.2s;
}

.invoice-card:hover {
  border-color: #3b82f6;
  box-shadow: 0 4px 14px rgba(59, 130, 246, 0.08);
}

.inv-card-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.inv-badge-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.inv-id {
  font-size: 12px;
  font-weight: 700;
  padding: 4px 8px;
  border-radius: 6px;
  background: var(--surface);
  border: 1px solid var(--outline);
  color: var(--ink);
}

.series-ref {
  font-size: 11px;
  color: var(--muted);
}

.status-pill {
  font-size: 11px;
  font-weight: 700;
  padding: 3px 10px;
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

.inv-card-content {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.inv-venue-info {
  display: flex;
  align-items: center;
  gap: 8px;
}

.court-icon {
  font-size: 18px;
  color: var(--pink-dark, #db2777);
}

.venue-texts {
  display: flex;
  align-items: center;
  gap: 6px;
}

.court-title {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
}

.venue-sub {
  font-size: 12px;
  color: var(--muted);
}

.inv-details-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
  padding: 12px;
  border-radius: 10px;
  background: var(--surface);
  border: 1px solid var(--outline);
}

@media (max-width: 640px) {
  .inv-details-grid {
    grid-template-columns: 1fr;
  }
}

.inv-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.col-lbl {
  font-size: 11px;
  color: var(--muted);
}

.col-val {
  font-size: 12px;
  font-weight: 600;
  color: var(--ink);
}

.col-val-amount {
  font-size: 14px;
  font-weight: 700;
  color: var(--pink-dark, #db2777);
}

.inv-card-actions {
  display: flex;
  justify-content: flex-end;
  border-top: 1px solid var(--outline);
  padding-top: 10px;
}
</style>

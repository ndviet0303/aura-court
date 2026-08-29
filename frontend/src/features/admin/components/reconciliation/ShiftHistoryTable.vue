<script setup lang="ts">
import type { CashShiftItem } from '../../composables/useAdminShiftReconciliation'

defineProps<{
  shifts: CashShiftItem[]
  statusFilter: string
  searchQuery: string
}>()

const emit = defineEmits<{
  (e: 'update:statusFilter', filter: any): void
  (e: 'update:searchQuery', q: string): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="shift-history-container">
    <!-- Header & Filter -->
    <div class="history-toolbar">
      <div class="status-tabs">
        <button
          class="status-btn"
          :class="{ active: statusFilter === 'all' }"
          @click="emit('update:statusFilter', 'all')"
        >
          Tất cả ca
        </button>
        <button
          class="status-btn"
          :class="{ active: statusFilter === 'matched' }"
          @click="emit('update:statusFilter', 'matched')"
        >
          Khớp 100%
        </button>
        <button
          class="status-btn"
          :class="{ active: statusFilter === 'exception' }"
          @click="emit('update:statusFilter', 'exception')"
        >
          Có chênh lệch
        </button>
        <button
          class="status-btn"
          :class="{ active: statusFilter === 'approved' }"
          @click="emit('update:statusFilter', 'approved')"
        >
          Đã duyệt đối soát
        </button>
      </div>

      <div class="search-box">
        <input
          :value="searchQuery"
          type="text"
          class="search-input"
          placeholder="Tìm theo mã ca, thu ngân, cơ sở..."
          @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
        />
      </div>
    </div>

    <!-- History Table -->
    <div class="history-table-card">
      <div class="table-header">
        <span>Mã Ca & Thu Ngân</span>
        <span>Cơ Sở Vận Hành</span>
        <span>Doanh Số Ca</span>
        <span>Tiền Mặt Đếm</span>
        <span>Chênh Lệch</span>
        <span>Trạng Thái Đối Soát</span>
      </div>

      <div class="table-body">
        <div
          v-for="s in shifts"
          :key="s.id"
          class="history-row"
        >
          <div class="code-col">
            <strong class="code font-mono">{{ s.code }}</strong>
            <span class="cashier">{{ s.cashier_name }} · {{ s.shift_name }}</span>
          </div>

          <div class="venue-col">
            <span>{{ s.venue_name }}</span>
            <span class="time-sub">{{ s.opened_at }}</span>
          </div>

          <div class="sales-col">
            <strong class="text-pink">{{ formatMoney(s.total_sales) }}</strong>
            <span class="orders-count">{{ s.orders_count }} đơn hàng</span>
          </div>

          <div class="cash-col">
            <strong>{{ formatMoney(s.declared_cash) }}</strong>
            <span class="expected-sub">Dự kiến: {{ formatMoney(s.expected_cash) }}</span>
          </div>

          <div class="diff-col">
            <strong :class="s.variance === 0 ? 'text-green' : s.variance < 0 ? 'text-red' : 'text-yellow'">
              {{ s.variance > 0 ? '+' : '' }}{{ formatMoney(s.variance) }}
            </strong>
          </div>

          <div class="status-col">
            <span
              class="status-badge"
              :class="`status-${s.reconciliation_status}`"
            >
              {{
                s.reconciliation_status === 'matched'
                  ? '✓ Khớp hoàn toàn'
                  : s.reconciliation_status === 'approved'
                  ? '✓ Đã duyệt lệch'
                  : '⚠️ Lệch tiền két'
              }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.shift-history-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.history-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.status-tabs {
  display: flex;
  gap: 8px;
}

.status-btn {
  padding: 8px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.status-btn.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
}

.search-box {
  flex: 1;
  max-width: 320px;
}

.search-input {
  width: 100%;
  padding: 8px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
}

.history-table-card {
  display: flex;
  flex-direction: column;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
  overflow: hidden;
}

.table-header {
  display: grid;
  grid-template-columns: 2fr 1.8fr 1.2fr 1.2fr 1.2fr 1.5fr;
  padding: 12px 16px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
}

.table-body {
  display: flex;
  flex-direction: column;
}

.history-row {
  display: grid;
  grid-template-columns: 2fr 1.8fr 1.2fr 1.2fr 1.2fr 1.5fr;
  padding: 14px 16px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 13px;
}

.history-row:last-child {
  border-bottom: 0;
}

.code-col, .venue-col, .sales-col, .cash-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cashier, .time-sub, .orders-count, .expected-sub {
  font-size: 11px;
  color: var(--muted);
}

.text-pink { color: var(--pink-dark, #db2777); }
.text-green { color: #10b981; }
.text-yellow { color: #f59e0b; }
.text-red { color: #ef4444; }

.status-badge {
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
}

.status-matched, .status-approved {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.status-exception_short, .status-exception_over {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}
</style>

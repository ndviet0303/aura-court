<script setup lang="ts">
import type { RecurringSeries } from '../../composables/useRecurringBooking'

defineProps<{
  seriesList: RecurringSeries[]
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="recurring-manager-container">
    <div class="manager-header">
      <h3 class="manager-title">📅 Quản lý Lịch Định Kỳ Đang Hoạt Động ({{ seriesList.length }})</h3>
      <p class="manager-sub">Kiểm tra lịch đặt sân hàng tuần và danh sách hóa đơn thanh toán hàng tháng.</p>
    </div>

    <div v-if="seriesList.length === 0" class="empty-state">
      Chưa có chuỗi đặt sân định kỳ nào được kích hoạt.
    </div>

    <div v-else class="series-items-list">
      <div
        v-for="s in seriesList"
        :key="s.id"
        class="series-card"
      >
        <div class="series-top">
          <span class="series-id font-mono">CHỦNG ĐỊNH KỲ #{{ s.id }}</span>
          <strong class="series-status text-green">● Đang hoạt động ({{ s.frequency }})</strong>
        </div>

        <!-- Invoices list -->
        <div v-if="s.invoices && s.invoices.length > 0" class="invoices-box">
          <h4 class="invoices-title">💳 Hóa Đơn Hàng Tháng:</h4>
          <div class="invoices-rows">
            <div v-for="inv in s.invoices" :key="inv.id" class="invoice-item-row">
              <span>Kỳ: {{ inv.period_start.slice(0, 10) }} ➔ {{ inv.period_end.slice(0, 10) }}</span>
              <strong class="text-pink">{{ formatMoney(inv.amount) }}</strong>
              <span class="inv-status">● {{ inv.status === 'paid' ? 'Đã đóng' : 'Chờ thanh toán' }}</span>
            </div>
          </div>
        </div>

        <!-- Occurrences preview -->
        <div v-if="s.occurrences && s.occurrences.length > 0" class="occurrences-box">
          <span class="occ-title">Các buổi sắp diễn ra:</span>
          <div class="occ-list">
            <span v-for="occ in s.occurrences" :key="occ.id" class="occ-chip">
              🏸 Buổi {{ occ.sequence }}: {{ occ.starts_at.slice(0, 10) }} ({{ occ.starts_at.slice(11, 16) }})
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
  gap: 16px;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
  padding: 20px;
}

.manager-title {
  margin: 0 0 4px;
  font-size: 16px;
  font-weight: 700;
}

.manager-sub {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
}

.empty-state {
  padding: 30px;
  text-align: center;
  font-size: 13px;
  color: var(--muted);
}

.series-items-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.series-card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.series-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.series-id {
  font-size: 12px;
  font-weight: 700;
}

.series-status {
  font-size: 12px;
}

.text-green { color: #10b981; }
.text-pink { color: var(--pink-dark, #db2777); }

.invoices-box {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  border-radius: 10px;
  background: var(--surface);
  border: 1px solid var(--outline);
}

.invoices-title {
  margin: 0;
  font-size: 13px;
  font-weight: 700;
}

.invoices-rows {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.invoice-item-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
}

.inv-status {
  font-size: 11px;
  color: #f59e0b;
  font-weight: 600;
}

.occurrences-box {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.occ-title {
  font-size: 11px;
  color: var(--muted);
}

.occ-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.occ-chip {
  padding: 4px 8px;
  border-radius: 6px;
  background: var(--surface);
  border: 1px solid var(--outline);
  font-size: 11px;
}
</style>

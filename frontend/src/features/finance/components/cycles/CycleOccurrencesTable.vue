<script setup lang="ts">
import type { OccurrenceItem } from '../../composables/usePaymentCycleDetail'

defineProps<{
  occurrences: OccurrenceItem[]
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="occurrences-table-card">
    <div class="card-header">
      <h3 class="title">🏸 Lịch Sử & Danh Sách Buổi Thi Đấu Trong Kỳ</h3>
      <span class="sub">Tổng cộng {{ occurrences.length }} buổi đã được xếp lịch</span>
    </div>

    <div class="occurrences-table">
      <div class="table-hdr">
        <span>Buổi Số</span>
        <span>Sân Thi Đấu</span>
        <span>Thời Gian Bắt Đầu - Kết Thúc</span>
        <span>Chi Phí Buổi</span>
        <span>Trạng Thái</span>
      </div>

      <div class="table-body">
        <div v-for="occ in occurrences" :key="occ.id" class="occurrence-row">
          <strong class="seq font-mono">Buổi #{{ occ.sequence }}</strong>
          <span>{{ occ.court_name || 'VIP 01' }}</span>
          <span>📅 {{ occ.starts_at.slice(0, 10) }} ({{ occ.starts_at.slice(11, 16) }} ➔ {{ occ.ends_at.slice(11, 16) }})</span>
          <strong class="text-pink">{{ formatMoney(occ.amount) }}</strong>
          <span class="status-confirmed">✓ {{ occ.status }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.occurrences-table-card {
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
  align-items: flex-end;
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

.occurrences-table {
  display: flex;
  flex-direction: column;
  border-radius: 12px;
  border: 1px solid var(--outline);
  overflow: hidden;
}

.table-hdr {
  display: grid;
  grid-template-columns: 1.2fr 1.5fr 2.5fr 1.5fr 1.2fr;
  padding: 12px 16px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
}

.occurrence-row {
  display: grid;
  grid-template-columns: 1.2fr 1.5fr 2.5fr 1.5fr 1.2fr;
  padding: 12px 16px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 13px;
}

.occurrence-row:last-child {
  border-bottom: 0;
}

.text-pink { color: var(--pink-dark, #db2777); }
.status-confirmed { color: #10b981; font-weight: 600; font-size: 12px; }
</style>

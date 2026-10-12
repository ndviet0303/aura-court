<script setup lang="ts">
import { ref, computed } from 'vue'
import { VENUE_TIME_ZONE, venueClock } from '@/core/utils/venueTime'
import type { OccurrenceItem, MonthlyInvoiceData } from '../../types/finance'

const props = defineProps<{
  occurrences: OccurrenceItem[]
  invoice?: MonthlyInvoiceData | null
}>()

const activeFilter = ref<'all' | 'upcoming' | 'completed'>('all')

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}

function formatDate(dateStr: string): string {
  if (!dateStr) return ''
  try {
    const d = new Date(dateStr)
    return d.toLocaleDateString('vi-VN', {
      timeZone: VENUE_TIME_ZONE,
      weekday: 'short',
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    })
  } catch {
    return dateStr.slice(0, 10)
  }
}

function formatTimeRange(startStr: string, endStr: string): string {
  if (!startStr) return ''
  const start = new Date(startStr).getTime()
  // A missing or non-positive end shows the default 2h session.
  const end = new Date(endStr).getTime() > start ? endStr : new Date(start + 2 * 60 * 60 * 1000)
  return `${venueClock(startStr)} ➔ ${venueClock(end)}`
}

function calculateDurationHours(startStr: string, endStr: string): string {
  try {
    const start = new Date(startStr).getTime()
    const end = new Date(endStr).getTime()
    const diff = (end - start) / (1000 * 60 * 60)
    return diff > 0 ? `${diff.toFixed(1)}h` : '2.0h'
  } catch {
    return '2.0h'
  }
}

const filteredOccurrences = computed(() => {
  if (activeFilter.value === 'all') return props.occurrences
  const now = new Date().toISOString()
  if (activeFilter.value === 'upcoming') {
    return props.occurrences.filter((occ) => occ.starts_at >= now)
  }
  return props.occurrences.filter((occ) => occ.starts_at < now)
})

const totalHours = computed(() => {
  let total = 0
  props.occurrences.forEach((occ) => {
    try {
      const start = new Date(occ.starts_at).getTime()
      const end = new Date(occ.ends_at).getTime()
      const diff = (end - start) / (1000 * 60 * 60)
      if (diff > 0) total += diff
      else total += 2
    } catch {
      total += 2
    }
  })
  return total.toFixed(1)
})
</script>

<template>
  <div class="occurrences-table-card">
    <!-- Header Section -->
    <div class="card-header">
      <div class="header-texts">
        <div class="title-with-icon">
          <span class="material-symbols-outlined header-sport-icon">calendar_month</span>
          <h3 class="title">Lịch Sử & Danh Sách Buổi Thi Đấu Trong Kỳ</h3>
        </div>
        <p class="sub">
          Tổng cộng {{ occurrences.length }} buổi đã được xếp lịch (khoảng {{ totalHours }} giờ thi
          đấu)
        </p>
      </div>

      <!-- Filter Tabs -->
      <div class="filter-pills-row" v-if="occurrences.length > 0">
        <button
          type="button"
          class="pill-btn"
          :class="{ active: activeFilter === 'all' }"
          @click="activeFilter = 'all'"
        >
          Tất cả ({{ occurrences.length }})
        </button>
        <button
          type="button"
          class="pill-btn"
          :class="{ active: activeFilter === 'upcoming' }"
          @click="activeFilter = 'upcoming'"
        >
          Sắp tới
        </button>
        <button
          type="button"
          class="pill-btn"
          :class="{ active: activeFilter === 'completed' }"
          @click="activeFilter = 'completed'"
        >
          Đã hoàn tất
        </button>
      </div>
    </div>

    <!-- Occurrences Table Container -->
    <div class="occurrences-table-wrapper">
      <div class="occurrences-table">
        <div class="table-hdr">
          <span class="th-col">Buổi Số</span>
          <span class="th-col">Sân Thi Đấu</span>
          <span class="th-col">Thời Gian Bắt Đầu - Kết Thúc</span>
          <span class="th-col">Thời Lượng</span>
          <span class="th-col">Chi Phí Buổi</span>
          <span class="th-col">Trạng Thái</span>
        </div>

        <div v-if="filteredOccurrences.length === 0" class="empty-filter-state">
          <span class="material-symbols-outlined empty-icon">event_busy</span>
          <p>Không có buổi thi đấu nào trong nhóm bộ lọc này.</p>
        </div>

        <div v-else class="table-body">
          <div v-for="occ in filteredOccurrences" :key="occ.id" class="occurrence-row">
            <!-- Buổi Số -->
            <div class="col-seq">
              <strong class="seq font-mono">Buổi #{{ occ.sequence }}</strong>
            </div>

            <!-- Sân Thi Đấu -->
            <div class="col-court">
              <span class="material-symbols-outlined court-bullet-icon">sports_tennis</span>
              <span class="court-name">{{
                occ.court_name || invoice?.series?.court?.name || 'VIP 01'
              }}</span>
            </div>

            <!-- Thời Gian -->
            <div class="col-time">
              <span class="date-text">
                <span class="material-symbols-outlined time-icon">event</span>
                {{ formatDate(occ.starts_at) }}
              </span>
              <span class="time-range font-mono">
                <span class="material-symbols-outlined time-icon">schedule</span>
                {{ formatTimeRange(occ.starts_at, occ.ends_at) }}
              </span>
            </div>

            <!-- Thời Lượng -->
            <div class="col-duration">
              <span class="duration-badge font-mono">{{
                calculateDurationHours(occ.starts_at, occ.ends_at)
              }}</span>
            </div>

            <!-- Chi Phí -->
            <div class="col-amount">
              <strong class="text-pink font-mono">{{ formatMoney(occ.amount) }}</strong>
            </div>

            <!-- Trạng Thái -->
            <div class="col-status">
              <span class="status-confirmed">
                <span class="material-symbols-outlined check-icon">check_circle</span>
                <span>✓ {{ occ.status === 'confirmed' ? 'Đã xác nhận' : occ.status }}</span>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Summary Footnote -->
    <div class="table-footnote">
      <div class="footnote-item">
        <span class="material-symbols-outlined fn-icon">verified_user</span>
        <span
          >Lịch thi đấu cố định được bảo lưu chỗ tự động và được điều chỉnh linh hoạt theo thỏa
          thuận hợp đồng.</span
        >
      </div>
    </div>
  </div>
</template>

<style scoped>
.occurrences-table-card {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 24px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: 12px;
}

.header-texts {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.header-sport-icon {
  font-family: 'Material Symbols Outlined' !important;
  font-size: 22px;
  color: var(--pink-dark, #db2777);
  line-height: 1;
}

.title {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
  color: var(--ink);
}

.sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.filter-pills-row {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.pill-btn {
  padding: 5px 12px;
  border-radius: 999px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  color: var(--muted);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.pill-btn:hover {
  color: var(--ink);
  background: var(--surface);
}

.pill-btn.active {
  background: var(--pink-dark, #db2777);
  border-color: var(--pink-dark, #db2777);
  color: #fff;
}

.occurrences-table-wrapper {
  overflow-x: auto;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.occurrences-table {
  display: flex;
  flex-direction: column;
  min-width: 720px;
}

.table-hdr {
  display: grid;
  grid-template-columns: 1.1fr 1.6fr 2.5fr 1fr 1.4fr 1.3fr;
  padding: 12px 16px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
}

.table-body {
  display: flex;
  flex-direction: column;
}

.occurrence-row {
  display: grid;
  grid-template-columns: 1.1fr 1.6fr 2.5fr 1fr 1.4fr 1.3fr;
  padding: 14px 16px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 13px;
  transition: background 0.15s;
}

.occurrence-row:hover {
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.occurrence-row:last-child {
  border-bottom: 0;
}

.col-seq .seq {
  font-size: 12px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  border: 1px solid var(--outline);
  color: var(--ink);
}

.col-court {
  display: flex;
  align-items: center;
  gap: 6px;
}

.court-bullet-icon {
  font-family: 'Material Symbols Outlined' !important;
  font-size: 16px;
  color: var(--pink-dark, #db2777);
  line-height: 1;
}

.court-name {
  font-weight: 600;
  color: var(--ink);
}

.col-time {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.date-text,
.time-range {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
}

.time-icon {
  font-family: 'Material Symbols Outlined' !important;
  font-size: 14px;
  color: var(--muted);
  line-height: 1;
}

.time-range {
  color: var(--muted);
  font-size: 11px;
}

.duration-badge {
  display: inline-block;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 4px;
  background: rgba(59, 130, 246, 0.1);
  color: #3b82f6;
}

.text-pink {
  color: var(--pink-dark, #db2777);
  font-size: 14px;
  font-weight: 700;
}

.status-confirmed {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: #10b981;
  font-weight: 700;
  font-size: 12px;
  background: rgba(16, 185, 129, 0.12);
  border: 1px solid rgba(16, 185, 129, 0.25);
  padding: 4px 10px;
  border-radius: 999px;
  user-select: none;
}

.check-icon {
  font-family: 'Material Symbols Outlined' !important;
  font-size: 14px;
  line-height: 1;
}

.empty-filter-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 32px 16px;
  gap: 8px;
  color: var(--muted);
  font-size: 13px;
}

.empty-icon {
  font-family: 'Material Symbols Outlined' !important;
  font-size: 32px;
  line-height: 1;
}

.table-footnote {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 4px;
}

.footnote-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--muted);
}

.fn-icon {
  font-family: 'Material Symbols Outlined' !important;
  font-size: 16px;
  color: #10b981;
  line-height: 1;
}
</style>

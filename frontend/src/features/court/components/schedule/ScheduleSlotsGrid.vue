<script setup lang="ts">
import { computed } from 'vue'
import type { AvailabilitySlot } from '../../types/court'
import type { ScheduleTimeFilter } from '../../composables/useCourtSchedule'

const props = defineProps<{
  slots: AvailabilitySlot[]
  selectedSlots: AvailabilitySlot[]
  timeFilter: ScheduleTimeFilter
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'toggleSlot', slot: AvailabilitySlot): void
  (e: 'updateTimeFilter', filter: ScheduleTimeFilter): void
  (e: 'quickSelect', hours: number): void
  (e: 'clearSelected'): void
}>()

function isSelected(slot: AvailabilitySlot) {
  return props.selectedSlots.some((s) => s.startsAt === slot.startsAt)
}

function isPeakHour(slot: AvailabilitySlot): boolean {
  try {
    const hour = Number(
      new Date(slot.startsAt).toLocaleTimeString('en-US', {
        timeZone: 'Asia/Ho_Chi_Minh',
        hour: '2-digit',
        hour12: false,
      })
    )
    return hour >= 17 && hour <= 21
  } catch {
    return false
  }
}

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}

const availableCount = computed(() => props.slots.filter((s) => s.available).length)

const filterButtons: Array<{ key: ScheduleTimeFilter; label: string; icon: string }> = [
  { key: 'all', label: 'Tất cả 24h', icon: 'schedule' },
  { key: 'morning', label: 'Sáng (06h-12h)', icon: 'wb_sunny' },
  { key: 'afternoon', label: 'Chiều (12h-17h)', icon: 'partly_cloudy_day' },
  { key: 'evening', label: 'Tối (17h-23h)', icon: 'nights_stay' },
  { key: 'peak', label: 'Giờ vàng 🔥', icon: 'local_fire_department' },
]
</script>

<template>
  <div class="schedule-slots-section">
    <!-- Header & Action Row -->
    <div class="slots-header-bar">
      <div class="header-left">
        <div class="header-icon-wrap">
          <span class="material-symbols-outlined icon-20">grid_view</span>
        </div>
        <div class="title-col">
          <h3 class="section-title">Khung Giờ Thi Đấu</h3>
          <span class="available-badge">
            <strong class="font-mono">{{ availableCount }}</strong> / {{ slots.length }} khung giờ sẵn sàng
          </span>
        </div>
      </div>

      <!-- Quick Action Buttons -->
      <div class="quick-actions-row">
        <button
          type="button"
          class="btn-quick"
          @click="emit('quickSelect', 1)"
          title="Chọn nhanh 1 giờ trống gần nhất"
        >
          <span class="material-symbols-outlined icon-14">flash_on</span>
          <span>Chọn 1h</span>
        </button>
        <button
          type="button"
          class="btn-quick"
          @click="emit('quickSelect', 2)"
          title="Chọn nhanh 2 giờ trống liền kề"
        >
          <span class="material-symbols-outlined icon-14">flash_on</span>
          <span>Chọn 2h</span>
        </button>
        <button
          v-if="selectedSlots.length > 0"
          type="button"
          class="btn-quick btn-clear"
          @click="emit('clearSelected')"
          title="Bỏ chọn tất cả giờ"
        >
          <span class="material-symbols-outlined icon-14">close</span>
          <span>Bỏ chọn ({{ selectedSlots.length }})</span>
        </button>
      </div>
    </div>

    <!-- Filter Tabs Row -->
    <div class="filter-tabs-row">
      <button
        v-for="f in filterButtons"
        :key="f.key"
        type="button"
        class="filter-tab-btn"
        :class="{ active: timeFilter === f.key }"
        @click="emit('updateTimeFilter', f.key)"
      >
        <span class="material-symbols-outlined tab-icon">{{ f.icon }}</span>
        <span>{{ f.label }}</span>
      </button>
    </div>

    <!-- Status Legend -->
    <div class="section-legend">
      <div class="legend-item"><span class="box-legend available"></span> Còn trống</div>
      <div class="legend-item"><span class="box-legend selected"></span> Đang chọn</div>
      <div class="legend-item"><span class="box-legend peak"></span> Giờ vàng (Peak)</div>
      <div class="legend-item"><span class="box-legend occupied"></span> Đã có người đặt</div>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading" class="slots-skeleton-grid">
      <div v-for="i in 12" :key="i" class="slot-skeleton-item shimmer"></div>
    </div>

    <!-- Empty State -->
    <div v-else-if="slots.length === 0" class="empty-slots">
      <span class="material-symbols-outlined empty-icon">event_busy</span>
      <p class="empty-title">Không tìm thấy khung giờ phù hợp</p>
      <p class="empty-sub">Hãy thử đổi sang ngày khác hoặc cơ sở/sân khác.</p>
    </div>

    <!-- Slots Grid -->
    <div v-else class="slots-grid">
      <button
        v-for="slot in slots"
        :key="slot.startsAt"
        type="button"
        class="slot-cell-btn"
        :class="{
          'is-available': slot.available,
          'is-occupied': !slot.available,
          'is-selected': isSelected(slot),
          'is-peak': isPeakHour(slot) && slot.available,
        }"
        :disabled="!slot.available"
        @click="emit('toggleSlot', slot)"
      >
        <!-- Tag Header -->
        <div class="slot-tag-row">
          <span v-if="isPeakHour(slot) && slot.available && !isSelected(slot)" class="peak-tag">
            <span class="material-symbols-outlined icon-10">local_fire_department</span>
            <span>Giờ vàng</span>
          </span>
          <span v-else-if="isSelected(slot)" class="selected-tag">
            <span class="material-symbols-outlined icon-10">check</span>
            <span>Đã chọn</span>
          </span>
          <span v-else-if="!slot.available" class="occupied-tag">
            <span class="material-symbols-outlined icon-10">lock</span>
            <span>Đã kín</span>
          </span>
          <span v-else class="available-dot-tag">
            <span class="green-dot"></span>
            <span>Trống</span>
          </span>
        </div>

        <span class="s-time font-mono">
          {{ slot.startsAt.slice(11, 16) }} - {{ slot.endsAt.slice(11, 16) }}
        </span>
        <span class="s-price font-mono">{{ formatMoney(slot.price) }}</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.schedule-slots-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 22px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 6px 24px rgba(0, 0, 0, 0.04);
}

:global(html.dark) .schedule-slots-section {
  background: #1a1518;
  border-color: #382d33;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}

.slots-header-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header-icon-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: var(--primary-container);
  color: var(--primary);
}

:global(html.dark) .header-icon-wrap {
  background: rgba(229, 142, 159, 0.15);
  color: #e58e9f;
}

.title-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.section-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
}

.icon-20 {
  font-size: 20px;
}

.available-badge {
  font-size: 12px;
  color: var(--muted);
}

.available-badge strong {
  color: var(--success-text, #2e7d32);
}

:global(html.dark) .available-badge strong {
  color: #4ade80;
}

.quick-actions-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-quick {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-subtle, var(--surface));
  color: var(--ink);
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

:global(html.dark) .btn-quick {
  background: #231c20;
  border-color: #382d33;
  color: #faf7f3;
}

.btn-quick:hover {
  background: var(--soft);
  border-color: var(--accent);
  color: var(--accent);
  transform: translateY(-1px);
}

.btn-clear {
  color: var(--error-text, #c62828);
  border-color: rgba(198, 40, 40, 0.3);
}

.btn-clear:hover {
  background: var(--error-bg, #ffebee);
}

:global(html.dark) .btn-clear {
  color: #f87171;
  border-color: rgba(248, 113, 113, 0.3);
}

.filter-tabs-row {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  scrollbar-width: none;
  padding-bottom: 2px;
}

.filter-tabs-row::-webkit-scrollbar {
  display: none;
}

.filter-tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 16px;
  border-radius: 999px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--muted);
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s ease;
}

:global(html.dark) .filter-tab-btn {
  background: #231c20;
  border-color: #382d33;
}

.filter-tab-btn:hover {
  border-color: var(--accent);
  color: var(--ink);
}

.filter-tab-btn.active {
  background: var(--primary-container);
  border-color: var(--primary);
  color: var(--primary);
  font-weight: 800;
  box-shadow: 0 2px 10px rgba(107, 45, 63, 0.15);
}

:global(html.dark) .filter-tab-btn.active {
  background: #341a24;
  color: #f7a8b8;
  border-color: #e58e9f;
  box-shadow: 0 2px 10px rgba(229, 142, 159, 0.2);
}

.tab-icon {
  font-size: 15px;
}

.section-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  font-size: 12px;
  color: var(--muted);
  padding: 10px 14px;
  border-radius: 12px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

:global(html.dark) .section-legend {
  background: #151113;
  border-color: #2b2227;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
}

.box-legend {
  width: 12px;
  height: 12px;
  border-radius: 3px;
}

.box-legend.available {
  background: var(--surface);
  border: 1px solid var(--outline);
}

:global(html.dark) .box-legend.available {
  background: #231c20;
  border-color: #4a3d45;
}

.box-legend.selected {
  background: linear-gradient(135deg, #e5c382 0%, #d8b46e 100%);
}

.box-legend.peak {
  background: #fff3e0;
  border: 1px solid #ff9800;
}

:global(html.dark) .box-legend.peak {
  background: #3d2407;
  border-color: #fb923c;
}

.box-legend.occupied {
  background: rgba(0, 0, 0, 0.12);
  border: 1px solid rgba(0, 0, 0, 0.15);
}

:global(html.dark) .box-legend.occupied {
  background: rgba(255, 255, 255, 0.04);
  border-color: #2a2226;
}

.slots-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(138px, 1fr));
  gap: 12px;
}

.slot-cell-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
  padding: 10px 10px 12px;
  min-height: 82px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

:global(html.dark) .slot-cell-btn {
  background: #231c20;
  border-color: #382d33;
}

.slot-cell-btn.is-available:hover {
  border-color: var(--accent, #c9a15a);
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(201, 161, 90, 0.2);
}

.slot-cell-btn.is-peak {
  border-color: rgba(216, 180, 110, 0.4);
}

.slot-cell-btn.is-selected {
  background: linear-gradient(135deg, #e5c382 0%, #d8b46e 100%) !important;
  border-color: #fae0a2 !important;
  color: #1c1202 !important;
  font-weight: 800;
  box-shadow: 0 6px 22px rgba(216, 180, 110, 0.45) !important;
  transform: translateY(-3px);
}

.slot-cell-btn.is-selected .s-price {
  color: #241402 !important;
  font-weight: 800;
}

.slot-cell-btn.is-occupied {
  background: var(--surface-subtle, rgba(0, 0, 0, 0.03));
  border-color: transparent;
  color: var(--muted);
  cursor: not-allowed;
  opacity: 0.45;
}

:global(html.dark) .slot-cell-btn.is-occupied {
  background: rgba(255, 255, 255, 0.02);
}

.slot-tag-row {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 16px;
  width: 100%;
}

.peak-tag {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  font-size: 9px;
  font-weight: 800;
  padding: 1px 6px;
  border-radius: 4px;
  background: #fff3e0;
  color: #d97706;
}

:global(html.dark) .peak-tag {
  background: #3d2407;
  color: #fbbf24;
}

.selected-tag {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  font-size: 9px;
  font-weight: 800;
  padding: 1px 6px;
  border-radius: 4px;
  background: #1c1202;
  color: #ffd580;
}

.occupied-tag {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  font-size: 9px;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 4px;
  background: rgba(0, 0, 0, 0.08);
  color: var(--muted);
}

:global(html.dark) .occupied-tag {
  background: rgba(255, 255, 255, 0.06);
}

.available-dot-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 9.5px;
  font-weight: 700;
  color: var(--muted);
}

.green-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: #10b981;
}

.s-time {
  font-size: 13px;
  font-weight: 800;
}

.s-price {
  font-size: 11.5px;
  color: var(--muted);
}

.icon-10 {
  font-size: 10px;
}

.icon-14 {
  font-size: 14px;
}

.slots-skeleton-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(138px, 1fr));
  gap: 12px;
}

.slot-skeleton-item {
  height: 82px;
  border-radius: 14px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.05));
}

:global(html.dark) .slot-skeleton-item {
  background: rgba(255, 255, 255, 0.03);
}

.shimmer {
  animation: pulseShimmer 1.5s infinite ease-in-out;
}

@keyframes pulseShimmer {
  0% { opacity: 0.4; }
  50% { opacity: 0.8; }
  100% { opacity: 0.4; }
}

.empty-slots {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 40px 20px;
  text-align: center;
}

.empty-icon {
  font-size: 36px;
  color: var(--muted);
  margin-bottom: 8px;
}

.empty-title {
  margin: 0;
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
}

.empty-sub {
  margin: 4px 0 0;
  font-size: 12px;
  color: var(--muted);
}
</style>

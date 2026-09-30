<script setup lang="ts">
import { ref, computed } from 'vue'

export type CarouselDayItem = {
  iso: string
  dayNum: number
  dayName: string
  monthName?: string
  isToday?: boolean
  isWeekend?: boolean
}

const props = defineProps<{
  days: CarouselDayItem[]
  selectedDate: string
}>()

const emit = defineEmits<{
  (e: 'selectDate', iso: string): void
  (e: 'prevDay'): void
  (e: 'nextDay'): void
  (e: 'today'): void
}>()

const scrollContainer = ref<HTMLElement | null>(null)
const dateInputRef = ref<HTMLInputElement | null>(null)

const currentMonthYearLabel = computed(() => {
  try {
    const d = new Date(props.selectedDate)
    const month = d.toLocaleString('vi-VN', { month: 'long' })
    const year = d.getFullYear()
    return `${month.charAt(0).toUpperCase() + month.slice(1)}, ${year}`
  } catch {
    return ''
  }
})

function scroll(direction: 'left' | 'right') {
  if (!scrollContainer.value) return
  const offset = direction === 'left' ? -240 : 240
  scrollContainer.value.scrollBy({ left: offset, behavior: 'smooth' })
}

function openNativeDatePicker() {
  if (dateInputRef.value) {
    if ('showPicker' in HTMLInputElement.prototype) {
      dateInputRef.value.showPicker()
    } else {
      dateInputRef.value.click()
    }
  }
}

function onNativeDateChange(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.value) {
    emit('selectDate', target.value)
  }
}
</script>

<template>
  <div class="schedule-date-picker-card">
    <!-- Top Bar: Month/Year & Navigation Controls -->
    <div class="picker-top-bar">
      <div class="month-info">
        <div class="cal-icon-wrap">
          <span class="material-symbols-outlined cal-icon">calendar_month</span>
        </div>
        <div class="month-text-col">
          <span class="month-label">{{ currentMonthYearLabel }}</span>
          <span class="month-hint">Chọn ngày bạn muốn thi đấu</span>
        </div>
      </div>

      <div class="picker-actions">
        <button
          type="button"
          class="btn-nav-action btn-today"
          @click="emit('today')"
          title="Về ngày hôm nay"
        >
          <span class="today-dot"></span>
          <span>Hôm nay</span>
        </button>

        <div class="arrow-nav-group">
          <button
            type="button"
            class="btn-nav-action btn-icon-only"
            @click="emit('prevDay')"
            title="Ngày trước"
            aria-label="Ngày trước"
          >
            <span class="material-symbols-outlined icon-16">chevron_left</span>
          </button>

          <button
            type="button"
            class="btn-nav-action btn-icon-only"
            @click="emit('nextDay')"
            title="Ngày tiếp theo"
            aria-label="Ngày tiếp theo"
          >
            <span class="material-symbols-outlined icon-16">chevron_right</span>
          </button>
        </div>

        <div class="custom-date-wrap">
          <input
            ref="dateInputRef"
            type="date"
            :value="selectedDate"
            class="hidden-native-input"
            @change="onNativeDateChange"
          />
          <button
            type="button"
            class="btn-nav-action btn-calendar-jump"
            @click="openNativeDatePicker"
            title="Mở lịch chọn ngày tùy ý"
          >
            <span class="material-symbols-outlined icon-16">edit_calendar</span>
            <span class="jump-text">Lịch chi tiết</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Carousel with Clean Left/Right Scroll Controls -->
    <div class="carousel-container">
      <button
        type="button"
        class="scroll-step-btn left"
        @click="scroll('left')"
        aria-label="Cuộn danh sách sang trái"
      >
        <span class="material-symbols-outlined icon-16">arrow_back_ios_new</span>
      </button>

      <div ref="scrollContainer" class="date-carousel">
        <button
          v-for="d in days"
          :key="d.iso"
          type="button"
          class="date-card-btn"
          :class="{
            active: selectedDate === d.iso,
            'is-today': d.isToday,
            'is-weekend': d.isWeekend,
          }"
          @click="emit('selectDate', d.iso)"
        >
          <!-- Top Tag (Integrated into layout, never overlapping) -->
          <div class="card-header-badge">
            <span v-if="d.isToday" class="tag-today">Hôm nay</span>
            <span v-else-if="d.isWeekend" class="tag-weekend">Cuối tuần</span>
            <span v-else class="tag-spacer">{{ d.monthName }}</span>
          </div>

          <span class="day-name">{{ d.dayName }}</span>
          <strong class="day-num font-mono">{{ d.dayNum }}</strong>
          <span class="month-sub">{{ d.monthName }}</span>
        </button>
      </div>

      <button
        type="button"
        class="scroll-step-btn right"
        @click="scroll('right')"
        aria-label="Cuộn danh sách sang phải"
      >
        <span class="material-symbols-outlined icon-16">arrow_forward_ios</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.schedule-date-picker-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 20px;
  padding: 18px 22px;
  box-shadow: 0 6px 24px rgba(0, 0, 0, 0.04);
}

:global(html.dark) .schedule-date-picker-card {
  background: #1a1518;
  border-color: #382d33;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}

.picker-top-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.month-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.cal-icon-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border-radius: 12px;
  background: var(--primary-container);
  color: var(--primary);
}

:global(html.dark) .cal-icon-wrap {
  background: rgba(229, 142, 159, 0.15);
  color: #e58e9f;
}

.cal-icon {
  font-size: 20px;
}

.month-text-col {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.month-label {
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
  letter-spacing: -0.01em;
}

.month-hint {
  font-size: 11.5px;
  color: var(--muted);
}

.picker-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.arrow-nav-group {
  display: flex;
  align-items: center;
  gap: 4px;
}

.btn-nav-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  height: 34px;
  padding: 0 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-subtle, var(--surface));
  color: var(--ink);
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

:global(html.dark) .btn-nav-action {
  background: #231c20;
  border-color: #3d3137;
  color: #faf7f3;
}

.btn-nav-action:hover {
  background: var(--soft);
  border-color: var(--accent);
  color: var(--accent);
  transform: translateY(-1px);
}

.btn-icon-only {
  width: 34px;
  padding: 0;
}

.btn-today {
  background: var(--primary-container);
  color: var(--primary);
  border-color: transparent;
}

:global(html.dark) .btn-today {
  background: rgba(229, 142, 159, 0.15);
  color: #e58e9f;
  border-color: rgba(229, 142, 159, 0.3);
}

.today-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--primary);
}

:global(html.dark) .today-dot {
  background: #e58e9f;
}

.custom-date-wrap {
  position: relative;
}

.hidden-native-input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
  pointer-events: none;
}

.btn-calendar-jump {
  background: var(--surface-subtle, var(--surface));
}

.icon-16 {
  font-size: 16px;
}

/* Carousel Container with Integrated Scroll Buttons */
.carousel-container {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
}

.scroll-step-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 72px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-subtle, var(--surface));
  color: var(--muted);
  cursor: pointer;
  flex-shrink: 0;
  transition: all 0.2s ease;
}

:global(html.dark) .scroll-step-btn {
  background: #231c20;
  border-color: #382d33;
}

.scroll-step-btn:hover {
  background: var(--primary-container);
  color: var(--primary);
  border-color: var(--primary);
}

:global(html.dark) .scroll-step-btn:hover {
  background: rgba(229, 142, 159, 0.2);
  color: #e58e9f;
}

.date-carousel {
  display: flex;
  gap: 10px;
  overflow-x: auto;
  padding: 4px 2px;
  width: 100%;
  scrollbar-width: none;
}

.date-carousel::-webkit-scrollbar {
  display: none;
}

/* Date Card */
.date-card-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: space-between;
  min-width: 76px;
  height: 84px;
  padding: 6px 6px 8px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  flex-shrink: 0;
}

:global(html.dark) .date-card-btn {
  background: #231c20;
  border-color: #382d33;
  color: #e8e2e5;
}

.date-card-btn:hover:not(.active) {
  border-color: var(--accent);
  background: var(--soft);
  transform: translateY(-2px);
}

:global(html.dark) .date-card-btn:hover:not(.active) {
  background: #2c2329;
  border-color: #d8b46e;
}

.card-header-badge {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 14px;
}

.tag-today {
  font-size: 8.5px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 1px 6px;
  border-radius: 4px;
  background: var(--accent);
  color: #1c1202;
}

.tag-weekend {
  font-size: 8.5px;
  font-weight: 700;
  padding: 1px 5px;
  border-radius: 4px;
  background: rgba(201, 161, 90, 0.15);
  color: var(--accent);
}

.tag-spacer {
  font-size: 9px;
  opacity: 0.55;
  font-weight: 600;
}

.day-name {
  font-size: 11px;
  text-transform: uppercase;
  font-weight: 700;
  opacity: 0.8;
}

.day-num {
  font-size: 20px;
  font-weight: 800;
  line-height: 1;
}

.month-sub {
  font-size: 10px;
  font-weight: 600;
  opacity: 0.65;
}

/* Active State: Luxury Club Styling */
.date-card-btn.active {
  background: linear-gradient(145deg, #6B2D3F 0%, #4a1c29 100%) !important;
  border-color: #8c3b53 !important;
  color: #ffffff !important;
  box-shadow: 0 6px 20px rgba(107, 45, 63, 0.35) !important;
  transform: translateY(-2px);
}

.date-card-btn.active .day-name {
  color: #ffd5df;
  opacity: 1;
}

.date-card-btn.active .day-num {
  color: #ffffff;
}

.date-card-btn.active .month-sub {
  color: #ffd5df;
  opacity: 0.9;
}

.date-card-btn.active .tag-today {
  background: #fce892;
  color: #2b1d03;
}

/* Dark Mode Active State */
:global(html.dark) .date-card-btn.active {
  background: linear-gradient(145deg, #3d1b26 0%, #29121a 100%) !important;
  border: 1px solid #e58e9f !important;
  color: #ffffff !important;
  box-shadow: 0 6px 24px rgba(229, 142, 159, 0.25), inset 0 0 16px rgba(229, 142, 159, 0.1) !important;
}

:global(html.dark) .date-card-btn.active .day-name {
  color: #e58e9f;
  font-weight: 800;
}

:global(html.dark) .date-card-btn.active .day-num {
  color: #ffffff;
  text-shadow: 0 0 12px rgba(229, 142, 159, 0.4);
}

:global(html.dark) .date-card-btn.active .month-sub {
  color: #d8b46e;
}

:global(html.dark) .date-card-btn.active .tag-today {
  background: #d8b46e;
  color: #1c1202;
}

@media (max-width: 640px) {
  .schedule-date-picker-card {
    padding: 14px 16px;
  }
  .scroll-step-btn {
    display: none;
  }
  .jump-text {
    display: none;
  }
  .date-card-btn {
    min-width: 68px;
    height: 78px;
  }
  .day-num {
    font-size: 18px;
  }
}
</style>

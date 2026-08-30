<script setup lang="ts">
import { computed } from 'vue'

const daysInWeek = ['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'Chủ Nhật']

const weekDates = computed(() => {
  const now = new Date()
  const currentDay = now.getDay() // 0 = Sun, 1 = Mon...
  const mondayOffset = currentDay === 0 ? -6 : 1 - currentDay
  return daysInWeek.map((_, idx) => {
    const d = new Date(now)
    d.setDate(now.getDate() + mondayOffset + idx)
    return d.getDate()
  })
})
</script>

<template>
  <div class="pricing-calendar-container">
    <div class="calendar-header">
      <h3 class="section-title">📅 Lịch giá & Ma trận Khung Giờ (Pricing Calendar View)</h3>
      <span class="sub">Tháng 08 / 2026</span>
    </div>

    <div class="calendar-grid">
      <div v-for="(day, idx) in daysInWeek" :key="day" class="day-col">
        <div class="day-hdr">{{ day }} ({{ weekDates[idx] }})</div>
        <div class="slots-list">
          <div class="slot-item off-peak">
            <span class="time">06:00 - 11:00</span>
            <strong class="price">100.000 ₫</strong>
          </div>
          <div class="slot-item promo">
            <span class="time">11:00 - 14:00</span>
            <strong class="price text-blue">90.000 ₫</strong>
          </div>
          <div class="slot-item off-peak">
            <span class="time">14:00 - 17:00</span>
            <strong class="price">120.000 ₫</strong>
          </div>
          <div class="slot-item peak">
            <span class="time">17:00 - 21:00</span>
            <strong class="price text-pink">180.000 ₫</strong>
          </div>
          <div class="slot-item off-peak">
            <span class="time">21:00 - 23:00</span>
            <strong class="price">110.000 ₫</strong>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pricing-calendar-container {
  display: flex;
  flex-direction: column;
  gap: 14px;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
  padding: 20px;
}

.calendar-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.section-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.sub {
  font-size: 12px;
  color: var(--muted);
}

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 8px;
  overflow-x: auto;
}

.day-col {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 120px;
}

.day-hdr {
  font-size: 12px;
  font-weight: 700;
  padding: 6px 8px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border-radius: 8px;
  text-align: center;
}

.slots-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.slot-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 6px 8px;
  border-radius: 6px;
  border: 1px solid var(--outline);
  background: var(--surface);
  font-size: 11px;
}

.slot-item.peak {
  border-color: rgba(236, 72, 153, 0.3);
  background: rgba(236, 72, 153, 0.03);
}

.slot-item.promo {
  border-color: rgba(59, 130, 246, 0.3);
  background: rgba(59, 130, 246, 0.03);
}

.text-pink { color: var(--pink-dark, #db2777); }
.text-blue { color: #3b82f6; }
</style>

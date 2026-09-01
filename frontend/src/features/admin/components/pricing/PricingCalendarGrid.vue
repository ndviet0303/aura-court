<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const daysInWeek = computed(() => [
  t('admin.pricing.monday'),
  t('admin.pricing.tuesday'),
  t('admin.pricing.wednesday'),
  t('admin.pricing.thursday'),
  t('admin.pricing.friday'),
  t('admin.pricing.saturday'),
  t('admin.pricing.sunday'),
])

const now = new Date()
const currentMonth = String(now.getMonth() + 1).padStart(2, '0')
const currentYear = now.getFullYear()

const weekDates = computed(() => {
  const date = new Date()
  const currentDay = date.getDay() // 0 = Sun, 1 = Mon...
  const mondayOffset = currentDay === 0 ? -6 : 1 - currentDay
  return daysInWeek.value.map((_, idx) => {
    const d = new Date(date)
    d.setDate(date.getDate() + mondayOffset + idx)
    return d.getDate()
  })
})
</script>

<template>
  <div class="pricing-calendar-container">
    <div class="calendar-header">
      <div class="title-with-icon">
        <span class="material-symbols-outlined card-title-icon">calendar_month</span>
        <h3 class="section-title">{{ t('admin.pricing.calendarViewTitle') }}</h3>
      </div>
      <span class="sub font-mono">{{ t('admin.pricing.monthLabel', { month: currentMonth, year: currentYear }) }}</span>
    </div>

    <div class="calendar-grid">
      <div v-for="(day, idx) in daysInWeek" :key="day" class="day-col">
        <div class="day-hdr">{{ day }} ({{ weekDates[idx] }})</div>
        <div class="slots-list">
          <div class="slot-item off-peak">
            <span class="time font-mono">06:00 - 11:00</span>
            <strong class="price font-mono">100.000 ₫</strong>
          </div>
          <div class="slot-item promo">
            <span class="time font-mono">11:00 - 14:00</span>
            <strong class="price text-blue font-mono">90.000 ₫</strong>
          </div>
          <div class="slot-item off-peak">
            <span class="time font-mono">14:00 - 17:00</span>
            <strong class="price font-mono">120.000 ₫</strong>
          </div>
          <div class="slot-item peak">
            <span class="time font-mono">17:00 - 21:00</span>
            <strong class="price text-pink font-mono">180.000 ₫</strong>
          </div>
          <div class="slot-item off-peak">
            <span class="time font-mono">21:00 - 23:00</span>
            <strong class="price font-mono">110.000 ₫</strong>
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

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-title-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
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

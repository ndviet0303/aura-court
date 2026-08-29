<script setup lang="ts">
defineProps<{
  days: Array<{ iso: string; dayNum: number; dayName: string }>
  selectedDate: string
}>()

const emit = defineEmits<{
  (e: 'selectDate', iso: string): void
}>()
</script>

<template>
  <div class="schedule-date-picker">
    <div class="date-carousel">
      <button
        v-for="d in days"
        :key="d.iso"
        type="button"
        class="date-card-btn"
        :class="{ active: selectedDate === d.iso }"
        @click="emit('selectDate', d.iso)"
      >
        <span class="day-name">{{ d.dayName }}</span>
        <strong class="day-num font-mono">{{ d.dayNum }}</strong>
      </button>
    </div>
  </div>
</template>

<style scoped>
.schedule-date-picker {
  display: flex;
  overflow-x: auto;
  padding-bottom: 6px;
}

.date-carousel {
  display: flex;
  gap: 10px;
}

.date-card-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  min-width: 68px;
  padding: 12px 10px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  cursor: pointer;
  transition: all 0.15s;
}

.date-card-btn:hover {
  border-color: var(--pink-dark, #db2777);
  transform: translateY(-2px);
}

.date-card-btn.active {
  background: var(--pink-dark, #db2777);
  border-color: var(--pink-dark, #db2777);
  color: #fff;
  box-shadow: 0 4px 16px rgba(219, 39, 119, 0.3);
}

.day-name {
  font-size: 11px;
  text-transform: uppercase;
  opacity: 0.8;
}

.day-num {
  font-size: 18px;
}
</style>

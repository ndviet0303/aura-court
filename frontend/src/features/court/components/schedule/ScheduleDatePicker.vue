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

.date-card-btn:hover:not(.active) {
  border-color: var(--accent);
  background: var(--soft);
  transform: translateY(-2px);
}

.date-card-btn.active {
  background: var(--primary);
  border-color: var(--primary);
  color: var(--on-primary);
  box-shadow: 0 4px 16px rgba(107, 45, 63, 0.25);
  transform: translateY(-2px);
}

:global(html.dark) .date-card-btn.active {
  box-shadow: 0 4px 16px rgba(229, 142, 159, 0.3);
}

.day-name {
  font-size: 11px;
  text-transform: uppercase;
  font-weight: 600;
  opacity: 0.85;
}

.day-num {
  font-size: 18px;
  font-weight: 800;
}
</style>

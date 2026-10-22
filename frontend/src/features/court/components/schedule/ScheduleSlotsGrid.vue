<script setup lang="ts">
import StartTimePicker from '@/features/booking/components/StartTimePicker.vue'
import type { AvailabilitySlot } from '../../types/court'

defineProps<{
  slots: AvailabilitySlot[]
  selection: { startsAt: string; minutes: number } | null
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'pick', run: AvailabilitySlot[]): void
  (e: 'nextDay'): void
}>()
</script>

<template>
  <div class="schedule-slots-section">
    <div class="header-left">
      <div class="header-icon-wrap">
        <span class="material-symbols-outlined icon-20">schedule</span>
      </div>
      <h3 class="section-title">{{ $t('schedule.slotsSectionTitle') }}</h3>
    </div>

    <StartTimePicker
      :slots="slots"
      :selection="selection"
      :loading="loading"
      @pick="emit('pick', $event)"
      @next-day="emit('nextDay')"
    />
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

.section-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
}

.icon-20 {
  font-size: 20px;
}
</style>

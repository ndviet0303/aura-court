<script setup lang="ts">
import type { Court } from '../../types/court'

defineProps<{
  courts: Court[]
  selectedCourtId: number
}>()

const emit = defineEmits<{
  (e: 'selectCourt', id: number): void
}>()
</script>

<template>
  <div class="court-selector-bar">
    <label class="selector-lbl">Chọn sân đấu:</label>
    <div class="courts-pills-row">
      <button
        v-for="c in courts"
        :key="c.id"
        type="button"
        class="court-pill-btn"
        :class="{ active: selectedCourtId === c.id }"
        @click="emit('selectCourt', c.id)"
      >
        <span class="c-name">{{ c.name }}</span>
        <span class="c-venue">{{ c.venue?.name || 'Aura' }}</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.court-selector-bar {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.selector-lbl {
  font-size: 13px;
  font-weight: 700;
  color: var(--muted);
}

.courts-pills-row {
  display: flex;
  gap: 10px;
  overflow-x: auto;
  padding-bottom: 4px;
}

.court-pill-btn {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  padding: 10px 16px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  cursor: pointer;
  white-space: nowrap;
}

.court-pill-btn.active {
  border-color: var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.05);
}

.c-name {
  font-size: 13px;
  font-weight: 700;
}

.c-venue {
  font-size: 11px;
  color: var(--muted);
}
</style>

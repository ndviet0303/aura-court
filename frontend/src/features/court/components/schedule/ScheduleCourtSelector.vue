<script setup lang="ts">
import type { Court } from '../../types/court'

defineProps<{
  courts: Court[]
  selectedCourtId?: number | null
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
  transition: all 0.15s ease;
}

.court-pill-btn:hover:not(.active) {
  border-color: var(--accent);
  background: var(--soft);
}

.court-pill-btn.active {
  border-color: var(--primary);
  background: var(--primary-container);
  box-shadow: 0 2px 8px rgba(107, 45, 63, 0.12);
}

:global(html.dark) .court-pill-btn.active {
  box-shadow: 0 2px 8px rgba(229, 142, 159, 0.15);
}

.c-name {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
}

.court-pill-btn.active .c-name {
  color: var(--primary);
}

.c-venue {
  font-size: 11px;
  color: var(--muted);
}

.court-pill-btn.active .c-venue {
  color: var(--primary);
  opacity: 0.85;
}
</style>

<script setup lang="ts">
import type { MatchFixture } from '../../composables/useLiveScore'

defineProps<{
  fixtures: MatchFixture[]
  selectedId: number
}>()

const emit = defineEmits<{
  (e: 'select', id: number): void
}>()
</script>

<template>
  <div class="fixtures-selector-bar">
    <div
      v-for="f in fixtures"
      :key="f.id"
      class="fixture-tab-chip"
      :class="{ active: f.id === selectedId }"
      @click="emit('select', f.id)"
    >
      <span class="live-dot">● LIVE</span>
      <div class="f-info">
        <span class="court-lbl">{{ f.court_label }}</span>
        <strong class="players-vs">{{ f.player1.name.split(' ')[0] }} vs {{ f.player2.name.split(' ')[0] }}</strong>
      </div>
      <span class="current-set-score font-mono">{{ f.player1.current_score }} - {{ f.player2.current_score }}</span>
    </div>
  </div>
</template>

<style scoped>
.fixtures-selector-bar {
  display: flex;
  gap: 12px;
  overflow-x: auto;
  padding-bottom: 4px;
}

.fixture-tab-chip {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 16px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface);
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s;
}

.fixture-tab-chip.active {
  border-color: var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.05);
}

.live-dot {
  font-size: 10px;
  font-weight: 800;
  color: #ef4444;
  animation: pulse 1.5s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

.f-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.court-lbl {
  font-size: 11px;
  color: var(--muted);
}

.players-vs {
  font-size: 13px;
}

.current-set-score {
  font-size: 14px;
  font-weight: 800;
  color: var(--pink-dark, #db2777);
}
</style>

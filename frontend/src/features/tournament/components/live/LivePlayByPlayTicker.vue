<script setup lang="ts">
import type { MatchEvent } from '../../composables/useLiveScore'

defineProps<{
  events: MatchEvent[]
}>()
</script>

<template>
  <div class="live-play-ticker-card">
    <div class="card-header">
      <div class="hdr-title-wrap">
        <span class="material-symbols-outlined hdr-ico">bolt</span>
        <h3 class="title">Diễn Biến Từng Pha Cầu (Play-by-play)</h3>
      </div>
      <span class="sub">Cập nhật thời gian thực</span>
    </div>

    <div class="events-timeline">
      <div v-for="ev in events" :key="ev.id" class="event-node" :class="`type-${ev.type}`">
        <div class="time-col font-mono">{{ ev.time }}</div>
        <div class="event-desc-col">
          <strong class="p-name">{{ ev.player }}:</strong>
          <span class="p-action">{{ ev.action }}</span>
        </div>
        <div class="score-badge font-mono">{{ ev.score }}</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.live-play-ticker-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.hdr-title-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.hdr-ico {
  font-size: 18px;
  color: var(--accent, #c9a15a);
}

.title {
  margin: 0;
  font-size: 15px;
  font-weight: 750;
}

.sub {
  font-size: 12px;
  color: var(--muted);
}

.events-timeline {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 280px;
  overflow-y: auto;
}

.event-node {
  display: grid;
  grid-template-columns: 50px 1fr 70px;
  align-items: center;
  padding: 8px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  font-size: 12px;
  gap: 8px;
}

.time-col {
  font-size: 11px;
  color: var(--muted);
}

.event-desc-col {
  display: flex;
  gap: 6px;
}

.score-badge {
  font-weight: 800;
  color: var(--pink-dark, #db2777);
  text-align: right;
}
</style>

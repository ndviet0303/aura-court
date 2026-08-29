<script setup lang="ts">
import type { MatchFixture } from '../../composables/useLiveScore'

defineProps<{
  match: MatchFixture
}>()
</script>

<template>
  <div class="live-scoreboard-card">
    <div class="scoreboard-header">
      <div class="tourney-tag">
        <span class="live-badge">🔴 ĐANG DIỄN RA TRỰC TIẾP</span>
        <strong class="tourney-name">{{ match.tournament_name }}</strong> · 
        <span class="round-name">{{ match.round_label }}</span>
      </div>
      <div class="court-badge font-mono">📍 {{ match.court_label }}</div>
    </div>

    <div class="scoreboard-main-grid">
      <!-- Player 1 -->
      <div class="player-column" :class="{ 'is-serving': match.serving_player === 1 }">
        <div class="avatar-wrap">
          <img :src="match.player1.avatar" :alt="match.player1.name" class="p-avatar" />
          <span v-if="match.serving_player === 1" class="shuttle-badge" title="Đang cầm giao cầu">🏸</span>
        </div>
        <div class="p-info">
          <span class="p-seed">{{ match.player1.seed }} · {{ match.player1.club }}</span>
          <h3 class="p-name">{{ match.player1.name }}</h3>
        </div>
        <div class="p-big-score font-mono">{{ match.player1.current_score }}</div>
      </div>

      <!-- Set Indicator & History -->
      <div class="set-indicator-col">
        <span class="set-lbl">SET {{ match.current_set }}</span>
        <div class="sets-history-chips">
          <div
            v-for="s in match.sets_history"
            :key="s.set"
            class="set-chip"
            :class="{ 'is-current': s.set === match.current_set }"
          >
            <span class="s-label">Set {{ s.set }}</span>
            <span class="s-score font-mono">{{ s.p1 }} - {{ s.p2 }}</span>
          </div>
        </div>
      </div>

      <!-- Player 2 -->
      <div class="player-column" :class="{ 'is-serving': match.serving_player === 2 }">
        <div class="p-big-score font-mono">{{ match.player2.current_score }}</div>
        <div class="p-info text-right">
          <span class="p-seed">{{ match.player2.seed }} · {{ match.player2.club }}</span>
          <h3 class="p-name">{{ match.player2.name }}</h3>
        </div>
        <div class="avatar-wrap">
          <img :src="match.player2.avatar" :alt="match.player2.name" class="p-avatar" />
          <span v-if="match.serving_player === 2" class="shuttle-badge" title="Đang cầm giao cầu">🏸</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.live-scoreboard-card {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
}

.scoreboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  padding-bottom: 14px;
  border-bottom: 1px solid var(--outline);
}

.tourney-tag {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
}

.live-badge {
  font-size: 11px;
  font-weight: 800;
  color: #ef4444;
  background: rgba(239, 68, 68, 0.1);
  padding: 3px 8px;
  border-radius: 6px;
}

.court-badge {
  font-size: 12px;
  color: var(--muted);
}

.scoreboard-main-grid {
  display: grid;
  grid-template-columns: 1fr 140px 1fr;
  align-items: center;
  gap: 20px;
}

.player-column {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  border-radius: 16px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.player-column.is-serving {
  border-color: rgba(236, 72, 153, 0.4);
  background: rgba(236, 72, 153, 0.03);
}

.avatar-wrap {
  position: relative;
  width: 56px;
  height: 56px;
}

.p-avatar {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid var(--outline);
}

.shuttle-badge {
  position: absolute;
  bottom: -2px;
  right: -2px;
  font-size: 14px;
  background: var(--surface);
  border-radius: 50%;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
}

.p-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
}

.text-right {
  text-align: right;
}

.p-seed {
  font-size: 11px;
  color: var(--muted);
}

.p-name {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
}

.p-big-score {
  font-size: 42px;
  font-weight: 900;
  color: var(--pink-dark, #db2777);
  min-width: 50px;
  text-align: center;
}

.set-indicator-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
}

.set-lbl {
  font-size: 14px;
  font-weight: 900;
  letter-spacing: 1px;
  color: var(--pink-dark, #db2777);
}

.sets-history-chips {
  display: flex;
  flex-direction: column;
  gap: 6px;
  width: 100%;
}

.set-chip {
  display: flex;
  justify-content: space-between;
  padding: 4px 8px;
  border-radius: 6px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  font-size: 11px;
}

.set-chip.is-current {
  background: rgba(236, 72, 153, 0.1);
  color: var(--pink-dark, #db2777);
  font-weight: 700;
}

@media (max-width: 850px) {
  .scoreboard-main-grid {
    grid-template-columns: 1fr;
  }
}
</style>

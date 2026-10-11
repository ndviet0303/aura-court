<script setup lang="ts">
import type { MatchFixture } from '../../composables/useLiveScore'

defineProps<{
  match: MatchFixture
  pointState?: { type: string; player: number; text: string } | null
}>()
</script>

<template>
  <div class="live-scoreboard-card">
    <div class="scoreboard-header">
      <div class="tourney-tag">
        <span v-if="match.status === 'live'" class="live-badge">
          <span class="live-dot"></span>
          ĐANG DIỄN RA TRỰC TIẾP
        </span>
        <span v-else-if="match.status === 'completed'" class="completed-badge">
          🏆 ĐÃ KẾT THÚC
        </span>
        <span v-else class="scheduled-badge"> SẮP DIỄN RA </span>

        <strong class="tourney-name">{{ match.tournament_name }}</strong> ·
        <span class="round-name">{{ match.round_label }}</span>
      </div>

      <div class="hdr-right-meta">
        <div v-if="pointState" class="point-state-chip" :class="pointState.type">
          <span class="pulse-ring"></span>
          {{ pointState.text }}
        </div>

        <div class="court-badge font-mono">
          <span class="material-symbols-outlined mini-ico">location_on</span>
          {{ match.court_label }}
        </div>
      </div>
    </div>

    <div class="scoreboard-main-grid">
      <!-- Player 1 -->
      <div
        class="player-column"
        :class="{
          'is-serving': match.serving_player === 1 && match.status === 'live',
          'is-winner': match.winner === 1,
        }"
      >
        <div class="avatar-wrap">
          <img :src="match.player1.avatar" :alt="match.player1.name" class="p-avatar" />
          <span
            v-if="match.serving_player === 1 && match.status === 'live'"
            class="shuttle-badge"
            title="Đang cầm giao cầu"
          >
            <span class="material-symbols-outlined shuttle-ico">sports_tennis</span>
          </span>
          <span v-if="match.winner === 1" class="winner-trophy-badge" title="Chiến thắng">
            🏆
          </span>
        </div>
        <div class="p-info">
          <span class="p-seed">{{ match.player1.seed }} · {{ match.player1.club }}</span>
          <h3 class="p-name">{{ match.player1.name }}</h3>
          <span v-if="match.player1.sets_won > 0" class="sets-won-tag"
            >Thắng {{ match.player1.sets_won }} Set</span
          >
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
            :class="{
              'is-current': s.set === match.current_set && match.status === 'live',
              'p1-won': s.winner === 1,
              'p2-won': s.winner === 2,
            }"
          >
            <span class="s-label">Set {{ s.set }}</span>
            <span class="s-score font-mono">{{ s.p1 }} - {{ s.p2 }}</span>
          </div>
        </div>
      </div>

      <!-- Player 2 -->
      <div
        class="player-column"
        :class="{
          'is-serving': match.serving_player === 2 && match.status === 'live',
          'is-winner': match.winner === 2,
        }"
      >
        <div class="p-big-score font-mono">{{ match.player2.current_score }}</div>
        <div class="p-info text-right">
          <span class="p-seed">{{ match.player2.seed }} · {{ match.player2.club }}</span>
          <h3 class="p-name">{{ match.player2.name }}</h3>
          <span v-if="match.player2.sets_won > 0" class="sets-won-tag"
            >Thắng {{ match.player2.sets_won }} Set</span
          >
        </div>
        <div class="avatar-wrap">
          <img :src="match.player2.avatar" :alt="match.player2.name" class="p-avatar" />
          <span
            v-if="match.serving_player === 2 && match.status === 'live'"
            class="shuttle-badge"
            title="Đang cầm giao cầu"
          >
            <span class="material-symbols-outlined shuttle-ico">sports_tennis</span>
          </span>
          <span v-if="match.winner === 2" class="winner-trophy-badge" title="Chiến thắng">
            🏆
          </span>
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
  flex-wrap: wrap;
}

.hdr-right-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.point-state-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 850;
  letter-spacing: 0.3px;
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
  border: 1px solid rgba(239, 68, 68, 0.35);
  animation: pulse-border 1.5s infinite;
}

.point-state-chip.match_point {
  background: rgba(234, 179, 8, 0.18);
  color: #eab308;
  border-color: rgba(234, 179, 8, 0.45);
}

@keyframes pulse-border {
  0%,
  100% {
    opacity: 1;
    transform: scale(1);
  }
  50% {
    opacity: 0.75;
    transform: scale(1.02);
  }
}

.live-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  font-weight: 800;
  color: #ef4444;
  background: rgba(239, 68, 68, 0.1);
  padding: 3px 8px;
  border-radius: 6px;
}

.completed-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  font-weight: 800;
  color: #10b981;
  background: rgba(16, 185, 129, 0.12);
  padding: 3px 8px;
  border-radius: 6px;
}

.scheduled-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  font-weight: 750;
  color: var(--muted);
  background: var(--surface-container, rgba(0, 0, 0, 0.05));
  padding: 3px 8px;
  border-radius: 6px;
}

.live-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #ef4444;
  animation: pulse-live 1.4s infinite;
}

@keyframes pulse-live {
  0% {
    transform: scale(0.9);
    opacity: 1;
  }
  50% {
    transform: scale(1.3);
    opacity: 0.5;
  }
  100% {
    transform: scale(0.9);
    opacity: 1;
  }
}

.court-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: var(--muted);
}

.mini-ico {
  font-size: 15px;
  color: var(--pink-dark, #db2777);
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
  background: var(--surface-container, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  transition: all 0.2s;
}

.player-column.is-serving {
  border-color: rgba(236, 72, 153, 0.5);
  background: rgba(236, 72, 153, 0.04);
}

.player-column.is-winner {
  border-color: rgba(16, 185, 129, 0.5);
  background: rgba(16, 185, 129, 0.05);
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
  bottom: -4px;
  right: -4px;
  width: 22px;
  height: 22px;
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
}

.winner-trophy-badge {
  position: absolute;
  top: -6px;
  right: -6px;
  font-size: 16px;
}

.shuttle-ico {
  font-size: 13px;
}

.p-info {
  display: flex;
  flex-direction: column;
  gap: 3px;
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

.sets-won-tag {
  font-size: 10.5px;
  font-weight: 750;
  color: var(--pink-dark, #db2777);
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
  border: 1px solid transparent;
}

.set-chip.is-current {
  background: rgba(236, 72, 153, 0.1);
  color: var(--pink-dark, #db2777);
  border-color: rgba(236, 72, 153, 0.3);
  font-weight: 700;
}

@media (max-width: 850px) {
  .scoreboard-main-grid {
    grid-template-columns: 1fr;
  }
}
</style>

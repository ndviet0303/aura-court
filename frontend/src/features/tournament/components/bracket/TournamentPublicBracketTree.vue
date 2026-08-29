<script setup lang="ts">
import type { BracketMatch } from '../../composables/useTournamentBracket'

defineProps<{
  quarters: BracketMatch[]
  semis: BracketMatch[]
  finalMatch: BracketMatch
}>()

const emit = defineEmits<{
  (e: 'selectMatch', match: BracketMatch): void
}>()
</script>

<template>
  <div class="bracket-tree-wrapper">
    <!-- Quarter Finals Column -->
    <div class="bracket-column">
      <h3 class="col-header">TỨ KẾT (Quarter-Finals)</h3>
      <div class="matches-stack">
        <div
          v-for="m in quarters"
          :key="m.id"
          class="bracket-match-node"
          @click="emit('selectMatch', m)"
        >
          <div class="node-meta">
            <span>{{ m.label }} · {{ m.time }}</span>
            <span class="court-txt">{{ m.court }}</span>
          </div>

          <div class="player-slot" :class="{ winner: m.player1.isWinner }">
            <div class="p-details">
              <img :src="m.player1.avatar" class="p-avt" />
              <span class="p-name">{{ m.player1.name }}</span>
            </div>
            <strong class="p-score font-mono">{{ m.player1.score }}</strong>
          </div>

          <div class="player-slot" :class="{ winner: m.player2.isWinner }">
            <div class="p-details">
              <img :src="m.player2.avatar" class="p-avt" />
              <span class="p-name">{{ m.player2.name }}</span>
            </div>
            <strong class="p-score font-mono">{{ m.player2.score }}</strong>
          </div>
        </div>
      </div>
    </div>

    <!-- Semi Finals Column -->
    <div class="bracket-column">
      <h3 class="col-header">BÁN KẾT (Semi-Finals)</h3>
      <div class="matches-stack">
        <div
          v-for="m in semis"
          :key="m.id"
          class="bracket-match-node"
          @click="emit('selectMatch', m)"
        >
          <div class="node-meta">
            <span>{{ m.label }} · {{ m.time }}</span>
            <span class="court-txt">{{ m.court }}</span>
          </div>

          <div class="player-slot" :class="{ winner: m.player1.isWinner }">
            <div class="p-details">
              <img :src="m.player1.avatar" class="p-avt" />
              <span class="p-name">{{ m.player1.name }}</span>
            </div>
            <strong class="p-score font-mono">{{ m.player1.score }}</strong>
          </div>

          <div class="player-slot" :class="{ winner: m.player2.isWinner }">
            <div class="p-details">
              <img :src="m.player2.avatar" class="p-avt" />
              <span class="p-name">{{ m.player2.name }}</span>
            </div>
            <strong class="p-score font-mono">{{ m.player2.score }}</strong>
          </div>
        </div>
      </div>
    </div>

    <!-- Final Match Column -->
    <div class="bracket-column">
      <h3 class="col-header highlight">CHUNG KẾT (Finals) 🏆</h3>
      <div class="matches-stack">
        <div
          class="bracket-match-node finals-node"
          @click="emit('selectMatch', finalMatch)"
        >
          <div class="node-meta">
            <span class="live-tag">🔴 LIVE · {{ finalMatch.time }}</span>
            <span class="court-txt">{{ finalMatch.court }}</span>
          </div>

          <div class="player-slot" :class="{ winner: finalMatch.player1.isWinner }">
            <div class="p-details">
              <img :src="finalMatch.player1.avatar" class="p-avt" />
              <span class="p-name">{{ finalMatch.player1.name }}</span>
            </div>
            <strong class="p-score font-mono">{{ finalMatch.player1.score }}</strong>
          </div>

          <div class="player-slot" :class="{ winner: finalMatch.player2.isWinner }">
            <div class="p-details">
              <img :src="finalMatch.player2.avatar" class="p-avt" />
              <span class="p-name">{{ finalMatch.player2.name }}</span>
            </div>
            <strong class="p-score font-mono">{{ finalMatch.player2.score }}</strong>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.bracket-tree-wrapper {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
  overflow-x: auto;
  padding: 10px 0;
}

.bracket-column {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 280px;
}

.col-header {
  margin: 0;
  font-size: 13px;
  font-weight: 800;
  color: var(--muted);
  text-align: center;
  padding: 8px;
  background: var(--surface);
  border-radius: 8px;
  border: 1px solid var(--outline);
}

.col-header.highlight {
  color: var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.05);
  border-color: rgba(236, 72, 153, 0.2);
}

.matches-stack {
  display: flex;
  flex-direction: column;
  gap: 16px;
  justify-content: space-around;
  flex: 1;
}

.bracket-match-node {
  display: flex;
  flex-direction: column;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  overflow: hidden;
  cursor: pointer;
  transition: all 0.15s;
}

.bracket-match-node:hover {
  border-color: var(--pink-dark, #db2777);
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
}

.finals-node {
  border-color: rgba(236, 72, 153, 0.4);
  box-shadow: 0 4px 16px rgba(236, 72, 153, 0.08);
}

.node-meta {
  display: flex;
  justify-content: space-between;
  padding: 6px 10px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border-bottom: 1px solid var(--outline);
  font-size: 10px;
  color: var(--muted);
}

.live-tag {
  color: #ef4444;
  font-weight: 800;
}

.player-slot {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 10px;
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
}

.player-slot:last-child {
  border-bottom: 0;
}

.player-slot.winner {
  font-weight: 700;
  background: rgba(16, 185, 129, 0.04);
}

.p-details {
  display: flex;
  align-items: center;
  gap: 8px;
}

.p-avt {
  width: 24px;
  height: 24px;
  border-radius: 50%;
}

.p-score {
  font-size: 13px;
  color: var(--pink-dark, #db2777);
}
</style>

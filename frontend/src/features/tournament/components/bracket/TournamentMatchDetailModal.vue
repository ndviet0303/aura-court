<script setup lang="ts">
import { RouterLink } from 'vue-router'
import type { BracketMatch } from '../../composables/useTournamentBracket'

defineProps<{
  show: boolean
  match: BracketMatch | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()
</script>

<template>
  <div v-if="show && match" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <div class="modal-header">
        <h3 class="modal-title">🏸 Chi Tiết Trận Đấu: {{ match.label }}</h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="match-meta-bar">
          <span>⏰ Thời gian: {{ match.time }}</span>
          <span>📍 {{ match.court }}</span>
          <span class="status-tag">● {{ match.status.toUpperCase() }}</span>
        </div>

        <div class="versus-display">
          <div class="p-card" :class="{ winner: match.player1.isWinner }">
            <img :src="match.player1.avatar" class="big-avt" />
            <strong class="p-name">{{ match.player1.name }}</strong>
            <span class="p-club">{{ match.player1.club }}</span>
            <span class="sets-str" v-if="match.player1.sets">Sets: {{ match.player1.sets.join(' - ') }}</span>
          </div>

          <div class="vs-divider">VS</div>

          <div class="p-card" :class="{ winner: match.player2.isWinner }">
            <img :src="match.player2.avatar" class="big-avt" />
            <strong class="p-name">{{ match.player2.name }}</strong>
            <span class="p-club">{{ match.player2.club }}</span>
            <span class="sets-str" v-if="match.player2.sets">Sets: {{ match.player2.sets.join(' - ') }}</span>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <RouterLink to="/tournaments/live" class="btn-live-link">
          🔴 Xem Live Score Trực Tiếp
        </RouterLink>
        <button class="btn-cancel" @click="emit('close')">Đóng</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.match-meta-bar {
  display: flex;
  justify-content: space-between;
  padding: 10px 14px;
  border-radius: 10px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  font-size: 12px;
  color: var(--muted);
  margin-bottom: 16px;
}

.status-tag {
  font-weight: 700;
  color: var(--pink-dark, #db2777);
}

.versus-display {
  display: grid;
  grid-template-columns: 1fr 40px 1fr;
  align-items: center;
  gap: 16px;
}

.p-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 16px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface);
  text-align: center;
}

.p-card.winner {
  border-color: #10b981;
  background: rgba(16, 185, 129, 0.03);
}

.big-avt {
  width: 54px;
  height: 54px;
  border-radius: 50%;
  margin-bottom: 4px;
}

.p-name {
  font-size: 14px;
}

.p-club, .sets-str {
  font-size: 11px;
  color: var(--muted);
}

.vs-divider {
  font-weight: 900;
  color: var(--muted);
  font-size: 14px;
  text-align: center;
}

.btn-live-link {
  padding: 8px 16px;
  border-radius: 8px;
  background: #ef4444;
  color: #fff;
  font-weight: 700;
  font-size: 12px;
  text-decoration: none;
}

.btn-cancel {
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}
</style>

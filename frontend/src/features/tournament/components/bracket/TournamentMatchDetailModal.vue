<script setup lang="ts">
import { AuraButton } from '@/shared/components'
import type { BracketMatch } from '../../composables/useTournamentBracket'

defineProps<{
  show: boolean
  match: BracketMatch | null
  tournamentId?: string | number | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()
</script>

<template>
  <div v-if="show && match" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <!-- Modal Header -->
      <div class="modal-header">
        <div class="header-title-wrap">
          <div class="header-ico-box">
            <span class="material-symbols-outlined">sports_score</span>
          </div>
          <div>
            <h3 class="modal-title">Chi Tiết Trận Đấu: {{ match.label }}</h3>
            <p class="modal-sub">Thông tin đối đầu, thời gian thi đấu và điểm số từng set.</p>
          </div>
        </div>
        <button type="button" class="modal-close-btn" aria-label="Đóng" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body">
        <div class="match-meta-bar">
          <span v-if="match.time" class="meta-tag">
            <span class="material-symbols-outlined mini-ico">schedule</span>
            {{ match.time }}
          </span>
          <span v-if="match.court" class="meta-tag">
            <span class="material-symbols-outlined mini-ico">location_on</span>
            {{ match.court }}
          </span>
          <span class="status-tag" :class="`status-${match.status}`">
            {{
              match.status === 'live'
                ? 'ĐANG DIỄN RA'
                : match.status === 'final'
                  ? 'KẾT THÚC'
                  : 'SẮP ĐẤU'
            }}
          </span>
        </div>

        <div class="versus-display">
          <!-- Player 1 Card -->
          <div class="p-card" :class="{ winner: match.player1.isWinner }">
            <div class="avatar-wrap">
              <img
                :src="match.player1.avatar"
                :alt="match.player1.name"
                class="big-avt"
                loading="lazy"
              />
              <span v-if="match.player1.seed" class="seed-badge">{{ match.player1.seed }}</span>
            </div>
            <strong class="p-name">{{ match.player1.name }}</strong>
            <span v-if="match.player1.club" class="p-club">{{ match.player1.club }}</span>

            <div
              class="score-display font-mono"
              :class="{ 'score-winner': match.player1.isWinner }"
            >
              {{ match.player1.score ?? '—' }}
            </div>

            <div v-if="match.player1.sets?.length" class="sets-box">
              <span v-for="(set, idx) in match.player1.sets" :key="idx" class="set-chip">
                {{ set }}
              </span>
            </div>
          </div>

          <!-- Divider -->
          <div class="vs-divider">
            <span class="vs-text">VS</span>
          </div>

          <!-- Player 2 Card -->
          <div class="p-card" :class="{ winner: match.player2.isWinner }">
            <div class="avatar-wrap">
              <img
                :src="match.player2.avatar"
                :alt="match.player2.name"
                class="big-avt"
                loading="lazy"
              />
              <span v-if="match.player2.seed" class="seed-badge">{{ match.player2.seed }}</span>
            </div>
            <strong class="p-name">{{ match.player2.name }}</strong>
            <span v-if="match.player2.club" class="p-club">{{ match.player2.club }}</span>

            <div
              class="score-display font-mono"
              :class="{ 'score-winner': match.player2.isWinner }"
            >
              {{ match.player2.score ?? '—' }}
            </div>

            <div v-if="match.player2.sets?.length" class="sets-box">
              <span v-for="(set, idx) in match.player2.sets" :key="idx" class="set-chip">
                {{ set }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <AuraButton variant="ghost" size="md" @click="emit('close')"> Đóng </AuraButton>

        <AuraButton
          v-if="tournamentId"
          :to="`/tournaments/${tournamentId}/live`"
          variant="primary"
          size="md"
          icon="scoreboard"
        >
          Xem Tỷ Số Trực Tiếp (Live Score)
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  padding: 20px;
}

.modal-card {
  width: 100%;
  max-width: 600px;
  max-height: 90vh;
  background: var(--surface);
  border-radius: 20px;
  border: 1px solid var(--outline);
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.3);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 24px;
  border-bottom: 1px solid var(--outline);
}

.header-title-wrap {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header-ico-box {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: rgba(219, 39, 119, 0.1);
  color: var(--pink-dark, #db2777);
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
}

.modal-sub {
  margin: 2px 0 0;
  font-size: 12px;
  color: var(--muted);
}

.modal-close-btn {
  background: none;
  border: none;
  color: var(--muted);
  cursor: pointer;
  padding: 6px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-close-btn:hover {
  background: var(--surface-container, rgba(0, 0, 0, 0.05));
  color: var(--ink);
}

.modal-body {
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 18px;
  overflow-y: auto;
}

.match-meta-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
  padding: 8px 14px;
  background: var(--surface-container, rgba(0, 0, 0, 0.03));
  border-radius: 10px;
  font-size: 12px;
}

.meta-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: var(--muted);
}

.mini-ico {
  font-size: 15px;
}

.status-tag {
  font-size: 10.5px;
  font-weight: 800;
  padding: 2px 8px;
  border-radius: 999px;
  letter-spacing: 0.5px;
}

.status-live {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
}
.status-final {
  background: rgba(16, 185, 129, 0.15);
  color: #10b981;
}
.status-upcoming,
.status-scheduled {
  background: rgba(100, 116, 139, 0.15);
  color: #64748b;
}

.versus-display {
  display: grid;
  grid-template-columns: 1fr 40px 1fr;
  align-items: center;
  gap: 12px;
}

.p-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 16px 12px;
  border-radius: 14px;
  background: var(--surface-container, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.p-card.winner {
  border-color: rgba(219, 39, 119, 0.4);
  background: rgba(219, 39, 119, 0.04);
}

.avatar-wrap {
  position: relative;
  margin-bottom: 8px;
}

.big-avt {
  width: 54px;
  height: 54px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid var(--outline);
}

.seed-badge {
  position: absolute;
  bottom: -2px;
  right: -2px;
  background: var(--surface);
  border: 1px solid var(--outline);
  font-size: 10px;
  font-weight: 800;
  padding: 1px 5px;
  border-radius: 999px;
}

.p-name {
  font-size: 13.5px;
  font-weight: 800;
  color: var(--ink);
  line-height: 1.3;
}

.p-club {
  font-size: 11px;
  color: var(--muted);
  margin-bottom: 8px;
}

.score-display {
  font-size: 26px;
  font-weight: 900;
  color: var(--muted);
  line-height: 1;
}

.score-winner {
  color: var(--pink-dark, #db2777);
}

.sets-box {
  display: flex;
  gap: 4px;
  margin-top: 6px;
}

.set-chip {
  padding: 2px 6px;
  background: var(--surface-container, rgba(0, 0, 0, 0.05));
  border-radius: 4px;
  font-size: 10.5px;
  font-family: monospace;
  color: var(--muted);
}

.vs-divider {
  display: flex;
  align-items: center;
  justify-content: center;
}

.vs-text {
  font-size: 12px;
  font-weight: 900;
  color: var(--muted);
  opacity: 0.6;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 24px;
  border-top: 1px solid var(--outline);
}
</style>

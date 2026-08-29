<script setup lang="ts">
import { ref } from 'vue'

const props = defineProps<{
  liveMatch: {
    matchCode: string
    courtName: string
    playerA: { name: string; club: string; seed?: number; score: number; sets: number[]; isServing?: boolean }
    playerB: { name: string; club: string; seed?: number; score: number; sets: number[]; isServing?: boolean }
    currentSet: number
    hasConflict: boolean
    conflictDesc: string
    isSwappedSides: boolean
    penalties: Array<{ player: string; type: string; reason: string; time: string }>
  }
}>()

const emit = defineEmits<{
  (e: 'incrementA'): void
  (e: 'decrementA'): void
  (e: 'incrementB'): void
  (e: 'decrementB'): void
  (e: 'switchEnds'): void
  (e: 'simulateConflict'): void
  (e: 'resolveConflict'): void
}>()

const showPenaltyModal = ref(false)
</script>

<template>
  <div class="referee-console-container">
    <!-- Header Row -->
    <div class="stitch-referee-header-row">
      <div class="match-badge-info">
        <span class="live-indicator-dot"></span>
        <strong class="match-code-tag">Live Match: {{ liveMatch.matchCode }}</strong>
        <span class="court-location-tag">{{ liveMatch.courtName }}</span>
      </div>

      <div class="referee-top-actions">
        <button class="btn-test-conflict" @click="emit('simulateConflict')">
          ⚡ Thử nghiệm xung đột điểm
        </button>
        <button class="btn-switch-ends" @click="emit('switchEnds')">
          🔄 Đổi sân (Switch Ends)
        </button>
      </div>
    </div>

    <!-- Conflict Warning Alert Banner -->
    <div v-if="liveMatch.hasConflict" id="conflict-alert" class="conflict-alert-banner">
      <div class="alert-content">
        <span class="alert-icon">⚠️</span>
        <div>
          <strong>Score Synchronization Conflict</strong>
          <p>{{ liveMatch.conflictDesc }}</p>
        </div>
      </div>
      <button class="btn-accept-remote" @click="emit('resolveConflict')">
        Chấp nhận điểm Remote
      </button>
    </div>

    <!-- Scoring Arena -->
    <div
      class="scoring-stage-arena"
      :class="{ 'is-swapped-sides': liveMatch.isSwappedSides }"
    >
      <!-- Player A Card -->
      <div class="referee-player-card player-left">
        <div class="player-header">
          <span v-if="liveMatch.playerA.isServing" class="serving-indicator">🏸 ĐANG GIAO CẦU</span>
          <h3 class="player-name">{{ liveMatch.playerA.name }}</h3>
          <span class="player-club">{{ liveMatch.playerA.club }}</span>
        </div>

        <div class="big-score-box">
          <button
            class="stitch-big-score-btn"
            title="Nhấn để cộng 1 điểm"
            @click="emit('incrementA')"
          >
            <span id="score-a" class="score-display">{{ liveMatch.playerA.score }}</span>
          </button>
          
          <div class="score-adjust-bar">
            <button class="btn-stitch-decrease" @click="emit('decrementA')">-1 Điểm</button>
          </div>
        </div>
      </div>

      <!-- Center Net Divider & Set Info -->
      <div class="arena-center-divider">
        <div class="set-indicator-pill">SET {{ liveMatch.currentSet }}</div>
        <div class="sets-history-row">
          <span class="set-history-score">{{ liveMatch.playerA.sets[0] || 21 }} - {{ liveMatch.playerB.sets[0] || 18 }}</span>
          <span class="set-history-score">{{ liveMatch.playerA.sets[1] || 19 }} - {{ liveMatch.playerB.sets[1] || 21 }}</span>
        </div>
      </div>

      <!-- Player B Card -->
      <div class="referee-player-card player-right">
        <div class="player-header">
          <span v-if="liveMatch.playerB.isServing" class="serving-indicator">🏸 ĐANG GIAO CẦU</span>
          <h3 class="player-name">{{ liveMatch.playerB.name }}</h3>
          <span class="player-club">{{ liveMatch.playerB.club }}</span>
        </div>

        <div class="big-score-box">
          <button
            class="stitch-big-score-btn"
            title="Nhấn để cộng 1 điểm"
            @click="emit('incrementB')"
          >
            <span id="score-b" class="score-display">{{ liveMatch.playerB.score }}</span>
          </button>

          <div class="score-adjust-bar">
            <button class="btn-stitch-decrease" @click="emit('decrementB')">-1 Điểm</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Referee Control Bar -->
    <div class="referee-bottom-toolbar">
      <button class="btn-referee-action-sec" @click="showPenaltyModal = true">
        🟨 Thẻ phạt & Lỗi kỹ thuật
      </button>
      <button class="btn-referee-action-sec">
        ⏱️ Tạm dừng (Injury/Timeout)
      </button>
      <button class="btn-referee-action-sec">
        🏸 Kết thúc Set đấu
      </button>
    </div>

    <!-- Penalties Modal -->
    <div v-if="showPenaltyModal" class="modal-backdrop" @click.self="showPenaltyModal = false">
      <div class="modal-card modal-penalty-card modal-sm">
        <div class="modal-header">
          <h3 class="modal-title">Thẻ Phạt & Lỗi Trọng Tài</h3>
          <button class="modal-close-btn" @click="showPenaltyModal = false">✕</button>
        </div>
        <div class="modal-body">
          <div class="penalty-options">
            <button class="penalty-btn card-yellow" @click="showPenaltyModal = false">
              🟨 Thẻ Vàng (Cảnh cáo)
            </button>
            <button class="penalty-btn card-red" @click="showPenaltyModal = false">
              🟥 Thẻ Đỏ (Phạt 1 điểm đối phương)
            </button>
            <button class="penalty-btn card-black" @click="showPenaltyModal = false">
              ⬛ Thẻ Đen (Truất quyền thi đấu)
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.referee-console-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
  padding: 16px;
}

.stitch-referee-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.match-badge-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.live-indicator-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #ef4444;
}

.match-code-tag {
  font-size: 15px;
}

.court-location-tag {
  font-size: 13px;
  color: var(--muted);
}

.referee-top-actions {
  display: flex;
  gap: 8px;
}

.btn-test-conflict,
.btn-switch-ends {
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.conflict-alert-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-radius: 10px;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid #ef4444;
  color: #ef4444;
}

.alert-content {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 13px;
}

.alert-icon {
  font-size: 20px;
}

.btn-accept-remote {
  padding: 6px 14px;
  border-radius: 8px;
  border: 0;
  background: #ef4444;
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.scoring-stage-arena {
  display: grid;
  grid-template-columns: 1fr 140px 1fr;
  gap: 16px;
  align-items: center;
  padding: 24px 16px;
  border-radius: 16px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.scoring-stage-arena.is-swapped-sides {
  direction: rtl;
}

.scoring-stage-arena.is-swapped-sides > * {
  direction: ltr;
}

.referee-player-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 20px;
  border-radius: 16px;
  background: var(--surface);
  border: 1px solid var(--outline);
  text-align: center;
}

.serving-indicator {
  display: inline-block;
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 6px;
  margin-bottom: 6px;
}

.player-name {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
}

.player-club {
  font-size: 12px;
  color: var(--muted);
}

.stitch-big-score-btn {
  width: 140px;
  height: 140px;
  border-radius: 20px;
  border: 2px solid var(--outline);
  background: var(--surface);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.06);
  transition: transform 0.1s, border-color 0.1s;
}

.stitch-big-score-btn:hover {
  border-color: var(--pink-dark, #db2777);
}

.stitch-big-score-btn:active {
  transform: scale(0.96);
}

.score-display {
  font-size: 64px;
  font-weight: 900;
  color: var(--pink-dark, #db2777);
  font-family: inherit;
}

.score-adjust-bar {
  margin-top: 8px;
}

.btn-stitch-decrease {
  padding: 4px 12px;
  border-radius: 6px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--muted);
  font-size: 11px;
  cursor: pointer;
}

.arena-center-divider {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.set-indicator-pill {
  padding: 6px 14px;
  border-radius: 20px;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 800;
  font-size: 13px;
}

.sets-history-row {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 12px;
  color: var(--muted);
}

.referee-bottom-toolbar {
  display: flex;
  justify-content: center;
  gap: 10px;
}

.btn-referee-action-sec {
  padding: 8px 16px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.penalty-options {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.penalty-btn {
  padding: 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  font-weight: 700;
  cursor: pointer;
  text-align: left;
}
</style>

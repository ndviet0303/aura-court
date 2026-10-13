<script setup lang="ts">
import type { MatchFixture, PointActionType } from '../../composables/useLiveScore'

defineProps<{
  match: MatchFixture
  pointState?: { type: string; player: number; text: string } | null
  intervalSeconds?: number | null
}>()

const emit = defineEmits<{
  (e: 'addPoint', playerIndex: 1 | 2, actionType?: PointActionType): void
  (e: 'undoPoint'): void
  (e: 'toggleServer'): void
  (e: 'switchSides'): void
  (e: 'penaltyCard', playerIndex: 1 | 2, card: 'yellow' | 'red'): void
  (e: 'triggerInterval', seconds: number): void
}>()
</script>

<template>
  <div class="umpire-control-deck">
    <!-- Top Header: Title, Status Notice, and Umpire Operations Toolbar -->
    <div class="deck-header">
      <div class="deck-title-wrap">
        <div class="umpire-badge">
          <span class="material-symbols-outlined ico">sports_score</span>
          <span>BẢNG TRỌNG TÀI BWF</span>
        </div>

        <div v-if="pointState" class="point-alert-pill" :class="pointState.type">
          <span class="pulse-spark"></span>
          {{ pointState.text }}
        </div>

        <div v-if="intervalSeconds !== null && intervalSeconds !== undefined" class="interval-pill">
          <span class="material-symbols-outlined timer-ico">timer</span>
          <span>HỘI Ý: {{ intervalSeconds }}s</span>
        </div>
      </div>

      <!-- Quick Operations Toolbar -->
      <div class="operations-toolbar">
        <button type="button" class="op-btn" title="Đổi bên giao cầu" @click="emit('toggleServer')">
          <span class="material-symbols-outlined op-ico">swap_calls</span>
          <span>Đổi Giao Cầu</span>
        </button>

        <button type="button" class="op-btn" title="Đổi sân thi đấu" @click="emit('switchSides')">
          <span class="material-symbols-outlined op-ico">sync_alt</span>
          <span>Đổi Sân</span>
        </button>

        <button
          type="button"
          class="op-btn"
          title="Nghỉ hội ý 60s"
          @click="emit('triggerInterval', 60)"
        >
          <span class="material-symbols-outlined op-ico">pause_circle</span>
          <span>Hội Ý (60s)</span>
        </button>

        <button
          type="button"
          class="op-btn op-undo"
          title="Hoàn tác điểm số vừa chấm"
          @click="emit('undoPoint')"
        >
          <span class="material-symbols-outlined op-ico">undo</span>
          <span>Hoàn Tác</span>
        </button>
      </div>
    </div>

    <!-- Match Scoring Grid (Player 1 Deck | Center Match Flow | Player 2 Deck) -->
    <div class="scoring-deck-grid">
      <!-- Player 1 Scoring Column -->
      <div class="player-deck" :class="{ 'has-serve': match.serving_player === 1 }">
        <div class="player-deck-header">
          <div class="p-meta">
            <img :src="match.player1.avatar" :alt="match.player1.name" class="p-deck-avatar" />
            <div class="p-text">
              <span class="p-seed">{{ match.player1.seed }}</span>
              <strong class="p-name">{{ match.player1.name }}</strong>
            </div>
          </div>

          <span v-if="match.serving_player === 1" class="serve-tag active">
            <span class="material-symbols-outlined serve-ico">sports_tennis</span>
            GIAO CẦU
          </span>
          <span v-else class="serve-tag receive"> NHẬN CẦU </span>
        </div>

        <!-- Big Hero +1 Score Button -->
        <button
          type="button"
          class="hero-score-btn btn-p1"
          :disabled="match.status === 'completed'"
          @click="emit('addPoint', 1, 'point')"
        >
          <div class="score-left">
            <span class="plus-icon">+1</span>
            <span class="score-caption">ĐIỂM TRỰC TIẾP</span>
          </div>
          <span class="live-score-preview font-mono">{{ match.player1.current_score }}</span>
        </button>

        <!-- Specific Point Type Action Chips -->
        <div class="point-action-chips">
          <button
            type="button"
            class="chip-action"
            title="Smash ghi điểm"
            :disabled="match.status === 'completed'"
            @click="emit('addPoint', 1, 'smash')"
          >
            <span class="material-symbols-outlined chip-ico">bolt</span>
            <span>Smash (+1)</span>
          </button>

          <button
            type="button"
            class="chip-action"
            title="Bỏ nhỏ / bắt lưới"
            :disabled="match.status === 'completed'"
            @click="emit('addPoint', 1, 'net')"
          >
            <span class="material-symbols-outlined chip-ico">grid_view</span>
            <span>Đánh Lưới (+1)</span>
          </button>

          <button
            type="button"
            class="chip-action"
            title="Giao bóng ăn điểm trực tiếp"
            :disabled="match.status === 'completed'"
            @click="emit('addPoint', 1, 'ace')"
          >
            <span class="material-symbols-outlined chip-ico">target</span>
            <span>Ace (+1)</span>
          </button>

          <button
            type="button"
            class="chip-action"
            title="Đối thủ đánh hỏng hoặc ra ngoài"
            :disabled="match.status === 'completed'"
            @click="emit('addPoint', 1, 'error')"
          >
            <span class="material-symbols-outlined chip-ico">cancel</span>
            <span>Đối Thủ Lỗi (+1)</span>
          </button>
        </div>

        <!-- Penalties & Correction Toolbar -->
        <div class="deck-footer-tools">
          <button
            type="button"
            class="tool-btn card-yellow"
            title="Phạt thẻ vàng cảnh cáo"
            @click="emit('penaltyCard', 1, 'yellow')"
          >
            <span class="card-icon yellow"></span>
            <span>Thẻ Vàng</span>
          </button>

          <button
            type="button"
            class="tool-btn card-red"
            title="Phạt thẻ đỏ (+1 điểm đối thủ)"
            @click="emit('penaltyCard', 1, 'red')"
          >
            <span class="card-icon red"></span>
            <span>Thẻ Đỏ</span>
          </button>
        </div>
      </div>

      <!-- Center Match Context Column -->
      <div class="center-flow-col">
        <div class="set-round-badge font-mono">
          <span class="set-main-txt">SET {{ match.current_set }}</span>
          <span class="set-sub-txt">Hiệp {{ match.current_set }}/3</span>
        </div>

        <!-- Sets Won Mini Counter -->
        <div class="sets-won-board">
          <span
            class="set-win-num font-mono"
            :class="{ leader: match.player1.sets_won > match.player2.sets_won }"
          >
            {{ match.player1.sets_won }}
          </span>
          <span class="sets-sep">-</span>
          <span
            class="set-win-num font-mono"
            :class="{ leader: match.player2.sets_won > match.player1.sets_won }"
          >
            {{ match.player2.sets_won }}
          </span>
        </div>

        <!-- History of Completed Sets -->
        <div class="sets-history-mini">
          <div
            v-for="s in match.sets_history"
            :key="s.set"
            class="mini-set-chip"
            :class="{ current: s.set === match.current_set }"
          >
            <span class="m-lbl">Set {{ s.set }}</span>
            <span class="m-val font-mono">{{ s.p1 }} - {{ s.p2 }}</span>
          </div>
        </div>

        <div v-if="match.status === 'completed'" class="completed-flag">🏆 ĐÃ KẾT THÚC</div>
      </div>

      <!-- Player 2 Scoring Column -->
      <div class="player-deck" :class="{ 'has-serve': match.serving_player === 2 }">
        <div class="player-deck-header">
          <div class="p-meta">
            <img :src="match.player2.avatar" :alt="match.player2.name" class="p-deck-avatar" />
            <div class="p-text">
              <span class="p-seed">{{ match.player2.seed }}</span>
              <strong class="p-name">{{ match.player2.name }}</strong>
            </div>
          </div>

          <span v-if="match.serving_player === 2" class="serve-tag active">
            <span class="material-symbols-outlined serve-ico">sports_tennis</span>
            GIAO CẦU
          </span>
          <span v-else class="serve-tag receive"> NHẬN CẦU </span>
        </div>

        <!-- Big Hero +1 Score Button -->
        <button
          type="button"
          class="hero-score-btn btn-p2"
          :disabled="match.status === 'completed'"
          @click="emit('addPoint', 2, 'point')"
        >
          <div class="score-left">
            <span class="plus-icon">+1</span>
            <span class="score-caption">ĐIỂM TRỰC TIẾP</span>
          </div>
          <span class="live-score-preview font-mono">{{ match.player2.current_score }}</span>
        </button>

        <!-- Specific Point Type Action Chips -->
        <div class="point-action-chips">
          <button
            type="button"
            class="chip-action"
            title="Smash ghi điểm"
            :disabled="match.status === 'completed'"
            @click="emit('addPoint', 2, 'smash')"
          >
            <span class="material-symbols-outlined chip-ico">bolt</span>
            <span>Smash (+1)</span>
          </button>

          <button
            type="button"
            class="chip-action"
            title="Bỏ nhỏ / bắt lưới"
            :disabled="match.status === 'completed'"
            @click="emit('addPoint', 2, 'net')"
          >
            <span class="material-symbols-outlined chip-ico">grid_view</span>
            <span>Đánh Lưới (+1)</span>
          </button>

          <button
            type="button"
            class="chip-action"
            title="Giao bóng ăn điểm trực tiếp"
            :disabled="match.status === 'completed'"
            @click="emit('addPoint', 2, 'ace')"
          >
            <span class="material-symbols-outlined chip-ico">target</span>
            <span>Ace (+1)</span>
          </button>

          <button
            type="button"
            class="chip-action"
            title="Đối thủ đánh hỏng hoặc ra ngoài"
            :disabled="match.status === 'completed'"
            @click="emit('addPoint', 2, 'error')"
          >
            <span class="material-symbols-outlined chip-ico">cancel</span>
            <span>Đối Thủ Lỗi (+1)</span>
          </button>
        </div>

        <!-- Penalties & Correction Toolbar -->
        <div class="deck-footer-tools">
          <button
            type="button"
            class="tool-btn card-yellow"
            title="Phạt thẻ vàng cảnh cáo"
            @click="emit('penaltyCard', 2, 'yellow')"
          >
            <span class="card-icon yellow"></span>
            <span>Thẻ Vàng</span>
          </button>

          <button
            type="button"
            class="tool-btn card-red"
            title="Phạt thẻ đỏ (+1 điểm đối thủ)"
            @click="emit('penaltyCard', 2, 'red')"
          >
            <span class="card-icon red"></span>
            <span>Thẻ Đỏ</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.umpire-control-deck {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border-radius: 20px;
  border: 1.5px solid var(--pink-dark, #db2777);
  background: var(--surface);
  box-shadow: 0 10px 30px rgba(219, 39, 119, 0.1);
}

/* Header & Operations Bar */
.deck-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  padding-bottom: 14px;
  border-bottom: 1px solid var(--outline);
}

.deck-title-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.umpire-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border-radius: 8px;
  background: rgba(219, 39, 119, 0.12);
  color: var(--pink-dark, #db2777);
  font-size: 11px;
  font-weight: 850;
  letter-spacing: 0.5px;
}

.umpire-badge .ico {
  font-size: 16px;
}

.point-alert-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.3px;
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
  border: 1px solid rgba(239, 68, 68, 0.3);
  animation: pulse 1.5s infinite;
}

.point-alert-pill.match_point {
  background: rgba(234, 179, 8, 0.18);
  color: #eab308;
  border-color: rgba(234, 179, 8, 0.4);
}

.interval-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 800;
  background: rgba(59, 130, 246, 0.15);
  color: #3b82f6;
  border: 1px solid rgba(59, 130, 246, 0.3);
}

.timer-ico {
  font-size: 14px;
}

.operations-toolbar {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.op-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-container, rgba(0, 0, 0, 0.03));
  color: var(--ink);
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.op-btn:hover {
  background: var(--surface-container, rgba(0, 0, 0, 0.08));
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
}

.op-btn.op-undo {
  color: #f59e0b;
}

.op-btn.op-undo:hover {
  border-color: #f59e0b;
  background: rgba(245, 158, 11, 0.1);
}

.op-ico {
  font-size: 16px;
}

/* Scoring Deck Grid */
.scoring-deck-grid {
  display: grid;
  grid-template-columns: 1fr 140px 1fr;
  gap: 16px;
  align-items: stretch;
}

.player-deck {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  border-radius: 16px;
  border: 1.5px solid var(--outline);
  background: var(--surface-container, rgba(0, 0, 0, 0.02));
  transition: all 0.2s ease;
}

.player-deck.has-serve {
  border-color: rgba(219, 39, 119, 0.5);
  background: rgba(219, 39, 119, 0.03);
}

.player-deck-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.p-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.p-deck-avatar {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  object-fit: cover;
  border: 1.5px solid var(--outline);
}

.p-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.p-seed {
  font-size: 10.5px;
  color: var(--muted);
}

.p-name {
  font-size: 14.5px;
  font-weight: 800;
  color: var(--ink);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.serve-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 10px;
  font-weight: 800;
  padding: 3px 8px;
  border-radius: 999px;
  letter-spacing: 0.5px;
}

.serve-tag.active {
  background: rgba(16, 185, 129, 0.15);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.serve-tag.receive {
  background: var(--surface-container, rgba(0, 0, 0, 0.05));
  color: var(--muted);
}

.serve-ico {
  font-size: 13px;
}

/* Big Hero +1 Score Button */
.hero-score-btn {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-radius: 14px;
  border: 0;
  cursor: pointer;
  transition:
    transform 0.15s ease,
    box-shadow 0.15s ease;
  color: #fff;
}

.hero-score-btn.btn-p1 {
  background: linear-gradient(135deg, #be185d 0%, #db2777 100%);
  box-shadow: 0 4px 16px rgba(219, 39, 119, 0.3);
}

.hero-score-btn.btn-p2 {
  background: linear-gradient(135deg, #0284c7 0%, #0ea5e9 100%);
  box-shadow: 0 4px 16px rgba(14, 165, 233, 0.3);
}

.hero-score-btn:hover:not(:disabled) {
  transform: translateY(-2px);
}

.hero-score-btn:active:not(:disabled) {
  transform: scale(0.98);
}

.hero-score-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.score-left {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
}

.plus-icon {
  font-size: 22px;
  font-weight: 900;
  line-height: 1;
}

.score-caption {
  font-size: 10px;
  font-weight: 750;
  letter-spacing: 0.6px;
  opacity: 0.9;
}

.live-score-preview {
  font-size: 34px;
  font-weight: 900;
}

/* Point Action Chips */
.point-action-chips {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
}

.chip-action {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 9px 10px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 11.5px;
  font-weight: 750;
  cursor: pointer;
  transition: all 0.15s ease;
}

.chip-action:hover:not(:disabled) {
  background: var(--surface-container, rgba(0, 0, 0, 0.05));
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
}

.chip-action:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.chip-ico {
  font-size: 15px;
}

/* Deck Footer Tools */
.deck-footer-tools {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  padding-top: 6px;
  border-top: 1px solid var(--outline);
}

.tool-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 8px;
  border-radius: 6px;
  border: 1px solid var(--outline);
  background: var(--surface);
  font-size: 11px;
  font-weight: 650;
  color: var(--muted);
  cursor: pointer;
  transition: all 0.15s;
}

.tool-btn:hover {
  border-color: var(--ink);
  color: var(--ink);
}

.card-icon {
  width: 10px;
  height: 13px;
  border-radius: 2px;
}

.card-icon.yellow {
  background: #facc15;
}

.card-icon.red {
  background: #ef4444;
}

/* Center Flow Column */
.center-flow-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 16px 8px;
  border-radius: 16px;
  background: var(--surface-container, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.set-round-badge {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}

.set-main-txt {
  font-size: 16px;
  font-weight: 900;
  color: var(--pink-dark, #db2777);
}

.set-sub-txt {
  font-size: 10px;
  font-weight: 600;
  color: var(--muted);
}

.sets-won-board {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 26px;
  font-weight: 900;
}

.set-win-num {
  color: var(--muted);
}

.set-win-num.leader {
  color: var(--pink-dark, #db2777);
}

.sets-sep {
  color: var(--muted);
  font-size: 18px;
}

.sets-history-mini {
  display: flex;
  flex-direction: column;
  gap: 4px;
  width: 100%;
}

.mini-set-chip {
  display: flex;
  justify-content: space-between;
  padding: 3px 6px;
  border-radius: 5px;
  background: var(--surface);
  border: 1px solid var(--outline);
  font-size: 10.5px;
}

.mini-set-chip.current {
  border-color: rgba(219, 39, 119, 0.4);
  color: var(--pink-dark, #db2777);
  font-weight: 750;
}

.completed-flag {
  font-size: 10.5px;
  font-weight: 800;
  color: #10b981;
  background: rgba(16, 185, 129, 0.12);
  padding: 4px 8px;
  border-radius: 6px;
  text-align: center;
}

@media (max-width: 900px) {
  .scoring-deck-grid {
    grid-template-columns: 1fr;
  }
}
</style>

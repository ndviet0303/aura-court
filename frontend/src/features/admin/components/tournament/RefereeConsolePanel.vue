<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

defineProps<{
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

const { t } = useI18n()
const showPenaltyModal = ref(false)
</script>

<template>
  <div class="referee-console-container">
    <!-- Header Row -->
    <div class="stitch-referee-header-row">
      <div class="match-badge-info">
        <span class="live-indicator-dot"></span>
        <strong class="match-code-tag font-mono">{{ t('admin.tournaments.liveMatchLabel', { code: liveMatch.matchCode }) }}</strong>
        <span class="court-location-tag">{{ liveMatch.courtName }}</span>
      </div>

      <div class="referee-top-actions">
        <button type="button" class="btn-test-conflict" @click="emit('simulateConflict')">
          <span class="material-symbols-outlined icon-14">flash_on</span>
          <span>{{ t('admin.tournaments.testConflictBtn') }}</span>
        </button>
        <button type="button" class="btn-switch-ends" @click="emit('switchEnds')">
          <span class="material-symbols-outlined icon-14">swap_horiz</span>
          <span>{{ t('admin.tournaments.switchEndsBtn') }}</span>
        </button>
      </div>
    </div>

    <!-- Conflict Warning Alert Banner -->
    <div v-if="liveMatch.hasConflict" id="conflict-alert" class="conflict-alert-banner">
      <div class="alert-content">
        <span class="material-symbols-outlined alert-icon">warning</span>
        <div>
          <strong>{{ t('admin.tournaments.conflictAlertTitle') }}</strong>
          <p>{{ liveMatch.conflictDesc }}</p>
        </div>
      </div>
      <button type="button" class="btn-accept-remote" @click="emit('resolveConflict')">
        {{ t('admin.tournaments.acceptRemoteScoreBtn') }}
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
          <span v-if="liveMatch.playerA.isServing" class="serving-indicator">
            <span class="material-symbols-outlined icon-12">sports_tennis</span>
            <span>{{ t('admin.tournaments.servingBadge') }}</span>
          </span>
          <h3 class="player-name">{{ liveMatch.playerA.name }}</h3>
          <span class="player-club">{{ liveMatch.playerA.club }}</span>
        </div>

        <div class="big-score-box">
          <button
            type="button"
            class="stitch-big-score-btn"
            :title="t('admin.tournaments.scoreClickHint')"
            @click="emit('incrementA')"
          >
            <span id="score-a" class="score-display font-mono">{{ liveMatch.playerA.score }}</span>
          </button>
          
          <div class="score-adjust-bar">
            <button type="button" class="btn-stitch-decrease" @click="emit('decrementA')">
              {{ t('admin.tournaments.decreaseScoreBtn') }}
            </button>
          </div>
        </div>
      </div>

      <!-- Center Net Divider & Set Info -->
      <div class="arena-center-divider">
        <div class="set-indicator-pill font-mono">SET {{ liveMatch.currentSet }}</div>
        <div class="sets-history-row font-mono">
          <span class="set-history-score">{{ liveMatch.playerA.sets[0] || 21 }} - {{ liveMatch.playerB.sets[0] || 18 }}</span>
          <span class="set-history-score">{{ liveMatch.playerA.sets[1] || 19 }} - {{ liveMatch.playerB.sets[1] || 21 }}</span>
        </div>
      </div>

      <!-- Player B Card -->
      <div class="referee-player-card player-right">
        <div class="player-header">
          <span v-if="liveMatch.playerB.isServing" class="serving-indicator">
            <span class="material-symbols-outlined icon-12">sports_tennis</span>
            <span>{{ t('admin.tournaments.servingBadge') }}</span>
          </span>
          <h3 class="player-name">{{ liveMatch.playerB.name }}</h3>
          <span class="player-club">{{ liveMatch.playerB.club }}</span>
        </div>

        <div class="big-score-box">
          <button
            type="button"
            class="stitch-big-score-btn"
            :title="t('admin.tournaments.scoreClickHint')"
            @click="emit('incrementB')"
          >
            <span id="score-b" class="score-display font-mono">{{ liveMatch.playerB.score }}</span>
          </button>

          <div class="score-adjust-bar">
            <button type="button" class="btn-stitch-decrease" @click="emit('decrementB')">
              {{ t('admin.tournaments.decreaseScoreBtn') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Referee Control Bar -->
    <div class="referee-bottom-toolbar">
      <button type="button" class="btn-referee-action-sec" @click="showPenaltyModal = true">
        <span class="material-symbols-outlined icon-16">style</span>
        <span>{{ t('admin.tournaments.penaltiesAndCardsBtn') }}</span>
      </button>
      <button type="button" class="btn-referee-action-sec">
        <span class="material-symbols-outlined icon-16">timer</span>
        <span>{{ t('admin.tournaments.timeoutBtn') }}</span>
      </button>
      <button type="button" class="btn-referee-action-sec">
        <span class="material-symbols-outlined icon-16">sports_score</span>
        <span>{{ t('admin.tournaments.endSetBtn') }}</span>
      </button>
    </div>

    <!-- Penalties Modal -->
    <div v-if="showPenaltyModal" class="modal-backdrop" @click.self="showPenaltyModal = false">
      <div class="modal-card modal-penalty-card modal-sm">
        <div class="modal-header">
          <div class="title-with-icon">
            <span class="material-symbols-outlined card-title-icon">gavel</span>
            <h3 class="modal-title">{{ t('admin.tournaments.penaltyModalTitle') }}</h3>
          </div>
          <button type="button" class="modal-close-btn" @click="showPenaltyModal = false">✕</button>
        </div>
        <div class="modal-body">
          <div class="penalty-options">
            <button type="button" class="penalty-btn card-yellow" @click="showPenaltyModal = false">
              <span class="square-tag yellow"></span>
              <span>{{ t('admin.tournaments.yellowCardBtn') }}</span>
            </button>
            <button type="button" class="penalty-btn card-red" @click="showPenaltyModal = false">
              <span class="square-tag red"></span>
              <span>{{ t('admin.tournaments.redCardBtn') }}</span>
            </button>
            <button type="button" class="penalty-btn card-black" @click="showPenaltyModal = false">
              <span class="square-tag black"></span>
              <span>{{ t('admin.tournaments.blackCardBtn') }}</span>
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

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-title-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
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
  display: inline-flex;
  align-items: center;
  gap: 4px;
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
  display: inline-flex;
  align-items: center;
  gap: 4px;
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
  display: inline-flex;
  align-items: center;
  gap: 6px;
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
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  font-weight: 700;
  cursor: pointer;
  text-align: left;
}

.square-tag {
  width: 16px;
  height: 20px;
  border-radius: 3px;
}

.square-tag.yellow { background: #facc15; }
.square-tag.red { background: #ef4444; }
.square-tag.black { background: #1f2937; }

.icon-16 { font-size: 16px; }
.icon-14 { font-size: 14px; }
.icon-12 { font-size: 12px; }
</style>

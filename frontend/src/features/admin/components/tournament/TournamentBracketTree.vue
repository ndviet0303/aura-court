<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { FixtureMatch } from '../../types/tournamentAdmin'

defineProps<{
  matches: FixtureMatch[]
  selectedRound: 'all' | 'r16' | 'qf' | 'sf' | 'final'
  isGenerating?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:selectedRound', round: 'all' | 'r16' | 'qf' | 'sf' | 'final'): void
  (e: 'selectMatch', match: FixtureMatch): void
  (e: 'regenerate'): void
}>()

const { t } = useI18n()
</script>

<template>
  <div class="bracket-tree-container">
    <!-- Toolbar: Round Filters & Actions -->
    <div class="bracket-toolbar">
      <div class="round-filters-group">
        <button
          type="button"
          class="round-filter-btn"
          :class="{ active: selectedRound === 'all' }"
          @click="emit('update:selectedRound', 'all')"
        >
          {{ t('admin.tournaments.allRounds') }}
        </button>
        <button
          type="button"
          class="round-filter-btn"
          :class="{ active: selectedRound === 'r16' }"
          @click="emit('update:selectedRound', 'r16')"
        >
          {{ t('admin.tournaments.roundR16') }}
        </button>
        <button
          type="button"
          class="round-filter-btn"
          :class="{ active: selectedRound === 'qf' }"
          @click="emit('update:selectedRound', 'qf')"
        >
          {{ t('admin.tournaments.roundQF') }}
        </button>
        <button
          type="button"
          class="round-filter-btn"
          :class="{ active: selectedRound === 'sf' }"
          @click="emit('update:selectedRound', 'sf')"
        >
          {{ t('admin.tournaments.roundSF') }}
        </button>
        <button
          type="button"
          class="round-filter-btn"
          :class="{ active: selectedRound === 'final' }"
          @click="emit('update:selectedRound', 'final')"
        >
          {{ t('admin.tournaments.roundFinal') }}
        </button>
      </div>

      <button
        type="button"
        class="btn-regenerate-bracket"
        :disabled="isGenerating"
        @click="emit('regenerate')"
      >
        <span v-if="isGenerating" class="flex-align">
          <span class="material-symbols-outlined icon-16 spin">sync</span>
          <span>{{ t('admin.tournaments.generatingBracket') }}</span>
        </span>
        <span v-else class="flex-align">
          <span class="material-symbols-outlined icon-16">alt_route</span>
          <span>{{ t('admin.tournaments.regenerateBracketBtn') }}</span>
        </span>
      </button>
    </div>

    <!-- Bracket Tree Visual View -->
    <div class="bracket-tree-scroll">
      <div class="bracket-rounds-columns">
        <!-- R16 Round -->
        <div
          v-if="selectedRound === 'all' || selectedRound === 'r16'"
          class="bracket-round-column"
        >
          <div class="round-header">
            <h4>{{ t('admin.tournaments.roundR16Header') }}</h4>
            <span class="round-meta">{{ t('admin.tournaments.roundR16Meta') }}</span>
          </div>

          <div class="round-matches-list">
            <div
              v-for="match in matches.filter(m => m.roundNumber === 1)"
              :key="match.id"
              class="match-node-card"
              :class="{ 'is-live': match.status === 'live', 'is-completed': match.status === 'completed' }"
              @click="emit('selectMatch', match)"
            >
              <div class="match-meta-header">
                <span class="match-court-name">{{ match.courtName }}</span>
                <span class="match-time-tag font-mono">{{ match.scheduledTime }}</span>
              </div>

              <div class="match-players-box">
                <div class="player-slot" :class="{ winner: match.player1.isWinner }">
                  <div class="player-info">
                    <span v-if="match.player1.seed" class="seed-badge">[{{ match.player1.seed }}]</span>
                    <span class="player-name">{{ match.player1.name }}</span>
                  </div>
                  <div class="player-scores font-mono">
                    <span v-for="(s, idx) in match.player1.sets" :key="idx" class="set-score">{{ s }}</span>
                  </div>
                </div>

                <div class="player-slot" :class="{ winner: match.player2.isWinner }">
                  <div class="player-info">
                    <span v-if="match.player2.seed" class="seed-badge">[{{ match.player2.seed }}]</span>
                    <span class="player-name">{{ match.player2.name }}</span>
                  </div>
                  <div class="player-scores font-mono">
                    <span v-for="(s, idx) in match.player2.sets" :key="idx" class="set-score">{{ s }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Quarter Finals -->
        <div
          v-if="selectedRound === 'all' || selectedRound === 'qf'"
          class="bracket-round-column"
        >
          <div class="round-header">
            <h4>{{ t('admin.tournaments.roundQFHeader') }}</h4>
            <span class="round-meta">{{ t('admin.tournaments.roundQFMeta') }}</span>
          </div>

          <div class="round-matches-list">
            <div
              v-for="match in matches.filter(m => m.roundNumber === 2)"
              :key="match.id"
              class="match-node-card"
              :class="{ 'is-live': match.status === 'live', 'is-completed': match.status === 'completed' }"
              @click="emit('selectMatch', match)"
            >
              <div class="match-meta-header">
                <span class="match-court-name">{{ match.courtName }}</span>
                <span v-if="match.status === 'live'" class="live-pill">{{ t('admin.tournaments.liveBadge') }}</span>
                <span v-else class="match-time-tag font-mono">{{ match.scheduledTime }}</span>
              </div>

              <div class="match-players-box">
                <div class="player-slot" :class="{ winner: match.player1.isWinner }">
                  <div class="player-info">
                    <span v-if="match.player1.seed" class="seed-badge">[{{ match.player1.seed }}]</span>
                    <span class="player-name">{{ match.player1.name }}</span>
                  </div>
                  <div class="player-scores font-mono">
                    <span v-for="(s, idx) in match.player1.sets" :key="idx" class="set-score">{{ s }}</span>
                    <span v-if="match.player1.currentScore !== undefined" class="current-score">{{ match.player1.currentScore }}</span>
                  </div>
                </div>

                <div class="player-slot" :class="{ winner: match.player2.isWinner }">
                  <div class="player-info">
                    <span v-if="match.player2.seed" class="seed-badge">[{{ match.player2.seed }}]</span>
                    <span class="player-name">{{ match.player2.name }}</span>
                  </div>
                  <div class="player-scores font-mono">
                    <span v-for="(s, idx) in match.player2.sets" :key="idx" class="set-score">{{ s }}</span>
                    <span v-if="match.player2.currentScore !== undefined" class="current-score">{{ match.player2.currentScore }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Semi Finals -->
        <div
          v-if="selectedRound === 'all' || selectedRound === 'sf'"
          class="bracket-round-column"
        >
          <div class="round-header">
            <h4>{{ t('admin.tournaments.roundSFHeader') }}</h4>
            <span class="round-meta">{{ t('admin.tournaments.roundSFMeta') }}</span>
          </div>

          <div class="round-matches-list">
            <div
              v-for="match in matches.filter(m => m.roundNumber === 3)"
              :key="match.id"
              class="match-node-card"
              @click="emit('selectMatch', match)"
            >
              <div class="match-meta-header">
                <span class="match-court-name">{{ match.courtName }}</span>
                <span class="match-time-tag font-mono">{{ match.scheduledTime }}</span>
              </div>

              <div class="match-players-box">
                <div class="player-slot">
                  <span class="player-name placeholder">{{ match.player1.name }}</span>
                </div>
                <div class="player-slot">
                  <span class="player-name placeholder">{{ match.player2.name }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Finals -->
        <div
          v-if="selectedRound === 'all' || selectedRound === 'final'"
          class="bracket-round-column"
        >
          <div class="round-header">
            <div class="flex-align">
              <span class="material-symbols-outlined icon-18 trophy-gold">emoji_events</span>
              <h4>{{ t('admin.tournaments.roundFinalHeader') }}</h4>
            </div>
            <span class="round-meta">{{ t('admin.tournaments.roundFinalMeta') }}</span>
          </div>

          <div class="round-matches-list">
            <div
              v-for="match in matches.filter(m => m.roundNumber === 4)"
              :key="match.id"
              class="match-node-card finals-card"
              @click="emit('selectMatch', match)"
            >
              <div class="match-meta-header">
                <span class="match-court-name">{{ match.courtName }}</span>
                <span class="match-time-tag font-mono">{{ match.scheduledTime }}</span>
              </div>

              <div class="match-players-box">
                <div class="player-slot">
                  <span class="player-name placeholder">{{ match.player1.name }}</span>
                </div>
                <div class="player-slot">
                  <span class="player-name placeholder">{{ match.player2.name }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.bracket-tree-container {
  display: flex;
  flex-direction: column;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
  overflow: hidden;
}

.bracket-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  border-bottom: 1px solid var(--outline);
  background: var(--surface);
  flex-wrap: wrap;
  gap: 10px;
}

.round-filters-group {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.round-filter-btn {
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.round-filter-btn.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
}

.btn-regenerate-bracket {
  padding: 8px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.flex-align {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.bracket-tree-scroll {
  padding: 20px;
  overflow-x: auto;
}

.bracket-rounds-columns {
  display: flex;
  gap: 24px;
  min-width: 800px;
}

.bracket-round-column {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.round-header {
  padding-bottom: 8px;
  border-bottom: 2px solid var(--outline);
}

.round-header h4 {
  margin: 0 0 2px;
  font-size: 13px;
  font-weight: 700;
}

.round-meta {
  font-size: 11px;
  color: var(--muted);
}

.round-matches-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.match-node-card {
  display: flex;
  flex-direction: column;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  padding: 10px;
  cursor: pointer;
  transition: all 0.15s;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.match-node-card:hover {
  border-color: var(--pink-dark, #db2777);
  transform: translateY(-2px);
}

.match-node-card.is-live {
  border-color: #ef4444;
  box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.15);
}

.match-meta-header {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  color: var(--muted);
  margin-bottom: 6px;
}

.live-pill {
  background: #ef4444;
  color: #fff;
  padding: 1px 6px;
  border-radius: 4px;
  font-weight: 800;
  font-size: 9px;
  animation: pulse 1.5s infinite;
}

.match-players-box {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.player-slot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 8px;
  border-radius: 6px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  font-size: 12px;
}

.player-slot.winner {
  font-weight: 700;
  background: rgba(16, 185, 129, 0.08);
}

.seed-badge {
  color: var(--pink-dark, #db2777);
  font-weight: 700;
  margin-right: 4px;
}

.player-scores {
  display: flex;
  gap: 4px;
}

.set-score {
  font-weight: 600;
}

.current-score {
  color: #ef4444;
  font-weight: 800;
}

.player-name.placeholder {
  color: var(--muted);
  font-style: italic;
}

.finals-card {
  border: 2px solid #f59e0b;
}

.trophy-gold {
  color: #f59e0b;
}

.icon-16 { font-size: 16px; }
.icon-18 { font-size: 18px; }

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  100% { transform: rotate(360deg); }
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}
</style>

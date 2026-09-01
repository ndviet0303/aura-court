<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { FixtureMatch } from '../../types/tournamentAdmin'
import { AuraButton, SkeletonLoader } from '@/shared/components'

const props = defineProps<{
  matches: FixtureMatch[]
  selectedRound: 'all' | 'r16' | 'qf' | 'sf' | 'final'
  isGenerating?: boolean
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:selectedRound', round: 'all' | 'r16' | 'qf' | 'sf' | 'final'): void
  (e: 'selectMatch', match: FixtureMatch): void
  (e: 'regenerate'): void
}>()

const { t } = useI18n()

const totalRounds = computed(() => {
  if (!props.matches || props.matches.length === 0) return 0
  return Math.max(...props.matches.map((m) => m.roundNumber || 1))
})

const distinctRounds = computed(() => {
  if (totalRounds.value === 0) return []
  return Array.from({ length: totalRounds.value }, (_, i) => i + 1)
})

function getRoundTitle(r: number): string {
  const max = totalRounds.value
  if (r === max) return t('admin.tournaments.roundFinalHeader') || 'CHUNG KẾT'
  if (r === max - 1) return t('admin.tournaments.roundSFHeader') || 'BÁN KẾT (SF)'
  if (r === max - 2) return t('admin.tournaments.roundQFHeader') || 'TỨ KẾT (QF)'
  return t('admin.tournaments.roundR16Header') || `VÒNG ${r}`
}

function getRoundMeta(r: number): string {
  const count = props.matches.filter((m) => m.roundNumber === r).length
  return t('admin.tournaments.roundMatchesCount', { count }) || `${count} Trận đấu`
}

function getMatchesForRound(r: number): FixtureMatch[] {
  return props.matches
    .filter((m) => m.roundNumber === r)
    .sort((a, b) => (a.position || 1) - (b.position || 1))
}

function shouldShowRound(r: number): boolean {
  if (props.selectedRound === 'all') return true
  const max = totalRounds.value
  if (props.selectedRound === 'final' && r === max) return true
  if (props.selectedRound === 'sf' && r === max - 1) return true
  if (props.selectedRound === 'qf' && r === max - 2) return true
  if (props.selectedRound === 'r16' && r === 1) return true
  return false
}
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
          v-if="totalRounds >= 4"
          type="button"
          class="round-filter-btn"
          :class="{ active: selectedRound === 'r16' }"
          @click="emit('update:selectedRound', 'r16')"
        >
          {{ t('admin.tournaments.roundR16') }}
        </button>
        <button
          v-if="totalRounds >= 3"
          type="button"
          class="round-filter-btn"
          :class="{ active: selectedRound === 'qf' }"
          @click="emit('update:selectedRound', 'qf')"
        >
          {{ t('admin.tournaments.roundQF') }}
        </button>
        <button
          v-if="totalRounds >= 2"
          type="button"
          class="round-filter-btn"
          :class="{ active: selectedRound === 'sf' }"
          @click="emit('update:selectedRound', 'sf')"
        >
          {{ t('admin.tournaments.roundSF') }}
        </button>
        <button
          v-if="totalRounds >= 1"
          type="button"
          class="round-filter-btn"
          :class="{ active: selectedRound === 'final' }"
          @click="emit('update:selectedRound', 'final')"
        >
          {{ t('admin.tournaments.roundFinal') }}
        </button>
      </div>

      <AuraButton
        variant="secondary"
        :disabled="isGenerating || loading"
        @click="emit('regenerate')"
      >
        <span v-if="isGenerating" class="material-symbols-outlined icon-16 spin">sync</span>
        <span v-else class="material-symbols-outlined icon-16">alt_route</span>
        <span>{{ isGenerating ? t('admin.tournaments.generatingBracket') : t('admin.tournaments.regenerateBracketBtn') }}</span>
      </AuraButton>
    </div>

    <!-- Skeleton Loading State -->
    <div v-if="loading || isGenerating" class="bracket-skeleton-grid">
      <div v-for="col in 3" :key="col" class="bracket-skeleton-col">
        <SkeletonLoader type="title" width="120px" height="18px" />
        <div v-for="m in Math.max(1, 4 - col)" :key="m" class="skeleton-match-card">
          <SkeletonLoader type="rect" width="100%" height="80px" rounded="12px" />
        </div>
      </div>
    </div>

    <!-- Bracket Tree Visual View -->
    <div v-else-if="matches.length > 0" class="bracket-tree-scroll">
      <div class="bracket-rounds-columns">
        <div
          v-for="r in distinctRounds"
          :key="r"
          v-show="shouldShowRound(r)"
          class="bracket-round-column"
        >
          <div class="round-header">
            <div class="flex-align">
              <span v-if="r === totalRounds" class="material-symbols-outlined icon-18 trophy-gold">emoji_events</span>
              <h4>{{ getRoundTitle(r) }}</h4>
            </div>
            <span class="round-meta">{{ getRoundMeta(r) }}</span>
          </div>

          <div class="round-matches-list">
            <div
              v-for="match in getMatchesForRound(r)"
              :key="match.id"
              class="match-node-card"
              :class="{
                'is-live': match.status === 'live',
                'is-completed': match.status === 'completed',
                'finals-card': r === totalRounds
              }"
              @click="emit('selectMatch', match)"
            >
              <div class="match-meta-header">
                <span class="match-court-name">{{ match.courtName }}</span>
                <span v-if="match.status === 'live'" class="live-pill">{{ t('admin.tournaments.liveBadge') }}</span>
                <span v-else class="match-time-tag font-mono">{{ match.scheduledTime }}</span>
              </div>

              <div class="match-players-box">
                <div class="player-slot" :class="{ winner: match.player1?.isWinner }">
                  <div class="player-info">
                    <span v-if="match.player1?.seed" class="seed-badge">[{{ match.player1.seed }}]</span>
                    <span class="player-name" :class="{ placeholder: !match.player1?.name || match.player1?.name === 'Chờ xác định' }">
                      {{ match.player1?.name || '—' }}
                    </span>
                  </div>
                  <div v-if="match.player1?.sets && match.player1.sets.length > 0" class="player-scores font-mono">
                    <span v-for="(s, idx) in match.player1.sets" :key="idx" class="set-score">{{ s }}</span>
                  </div>
                </div>

                <div class="player-slot" :class="{ winner: match.player2?.isWinner }">
                  <div class="player-info">
                    <span v-if="match.player2?.seed" class="seed-badge">[{{ match.player2.seed }}]</span>
                    <span class="player-name" :class="{ placeholder: !match.player2?.name || match.player2?.name === 'Chờ xác định' }">
                      {{ match.player2?.name || '—' }}
                    </span>
                  </div>
                  <div v-if="match.player2?.sets && match.player2.sets.length > 0" class="player-scores font-mono">
                    <span v-for="(s, idx) in match.player2.sets" :key="idx" class="set-score">{{ s }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="bracket-empty-state">
      <span class="material-symbols-outlined empty-icon">account_tree</span>
      <h4>{{ t('admin.tournaments.noMatchesFoundTitle') }}</h4>
      <p>{{ t('admin.tournaments.noMatchesFoundDesc') }}</p>
      <AuraButton variant="primary" @click="emit('regenerate')">
        <span class="material-symbols-outlined icon-16">alt_route</span>
        <span>{{ t('admin.tournaments.generateBracketBtn') }}</span>
      </AuraButton>
    </div>
  </div>
</template>

<style scoped>
.bracket-tree-container {
  display: flex;
  flex-direction: column;
  background: var(--surface, #ffffff);
  border-radius: 16px;
  border: 1px solid var(--outline, #e2e8f0);
  overflow: hidden;
}

.bracket-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  border-bottom: 1px solid var(--outline, #e2e8f0);
  background: var(--surface, #ffffff);
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
  border: 1px solid var(--outline, #e2e8f0);
  background: var(--surface, #ffffff);
  color: var(--ink, #0f172a);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.round-filter-btn:hover {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
}

.round-filter-btn.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
}

.bracket-skeleton-grid {
  display: flex;
  gap: 24px;
  padding: 24px;
  overflow-x: auto;
}

.bracket-skeleton-col {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 240px;
}

.skeleton-match-card {
  height: 80px;
}

.bracket-tree-scroll {
  padding: 24px;
  overflow-x: auto;
  min-height: 420px;
}

.bracket-rounds-columns {
  display: flex;
  gap: 32px;
  min-width: fit-content;
}

.bracket-round-column {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 260px;
  max-width: 280px;
}

.round-header {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding-bottom: 8px;
  border-bottom: 2px solid var(--outline, #e2e8f0);
}

.round-header h4 {
  margin: 0;
  font-size: 13px;
  font-weight: 800;
  color: var(--ink, #0f172a);
  letter-spacing: 0.04em;
}

.round-meta {
  font-size: 11px;
  color: var(--muted, #64748b);
}

.round-matches-list {
  display: flex;
  flex-direction: column;
  justify-content: space-around;
  flex: 1;
  gap: 20px;
}

.match-node-card {
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #e2e8f0);
  border-radius: 12px;
  padding: 12px;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
  transition: all 0.2s;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.match-node-card:hover {
  border-color: var(--pink-dark, #db2777);
  box-shadow: 0 4px 14px rgba(219, 39, 119, 0.12);
  transform: translateY(-2px);
}

.match-node-card.is-live {
  border-color: #ef4444;
  box-shadow: 0 0 0 1px #ef4444;
}

.match-node-card.finals-card {
  border: 2px solid #f59e0b;
}

.match-meta-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 11px;
  color: var(--muted, #64748b);
}

.live-pill {
  background: #ef4444;
  color: #fff;
  padding: 1px 6px;
  border-radius: 4px;
  font-size: 9px;
  font-weight: 800;
}

.match-players-box {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.player-slot {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 6px 8px;
  border-radius: 6px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  font-size: 12px;
}

.player-slot.winner {
  background: rgba(16, 185, 129, 0.1);
  font-weight: 700;
  color: #059669;
}

.player-info {
  display: flex;
  align-items: center;
  gap: 4px;
  overflow: hidden;
}

.seed-badge {
  font-size: 10px;
  color: var(--pink-dark, #db2777);
  font-weight: 700;
}

.player-name {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 140px;
}

.player-name.placeholder {
  color: var(--muted, #64748b);
  font-style: italic;
}

.player-scores {
  display: flex;
  gap: 4px;
}

.set-score {
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #e2e8f0);
  border-radius: 4px;
  padding: 1px 5px;
  font-size: 11px;
}

.bracket-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
  gap: 12px;
  text-align: center;
}

.empty-icon {
  font-size: 48px;
  color: var(--muted, #64748b);
}

.trophy-gold {
  color: #f59e0b;
}

.flex-align {
  display: flex;
  align-items: center;
  gap: 6px;
}

.icon-16 {
  font-size: 16px;
}

.icon-18 {
  font-size: 18px;
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  100% {
    transform: rotate(360deg);
  }
}
</style>

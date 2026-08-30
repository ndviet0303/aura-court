<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { FixtureMatch } from '../types/tournamentAdmin'
import { useTournamentBracketAdmin } from '../composables/useTournamentBracketAdmin'

import TournamentSetupWizard from '../components/tournament/TournamentSetupWizard.vue'
import TournamentBracketTree from '../components/tournament/TournamentBracketTree.vue'
import AthleteCheckInPanel from '../components/tournament/AthleteCheckInPanel.vue'
import RefereeConsolePanel from '../components/tournament/RefereeConsolePanel.vue'
import TournamentOverviewPanel from '../components/tournament/TournamentOverviewPanel.vue'
import ScoreUpdateModal from '../components/tournament/ScoreUpdateModal.vue'

const auth = useAuthStore()

const {
  tournament,
  currentWizardStep,
  activeSubNav,
  selectedRoundFilter,
  notice,
  error,
  isGenerating,
  checkInFilterStatus,
  checkInSearchQuery,
  checkInMetrics,
  filteredAthletes,
  matches,
  liveMatch,
  showNotice,
  showError,
  toggleAthleteCheckIn,
  incrementScoreA,
  decrementScoreA,
  incrementScoreB,
  decrementScoreB,
  switchEnds,
  simulateConflict,
  resolveConflict,
  regenerateBracketTree,
} = useTournamentBracketAdmin()

// Modal State
const selectedMatch = ref<FixtureMatch | null>(null)
const showScoreModal = ref(false)

function handleSelectMatch(match: FixtureMatch) {
  selectedMatch.value = match
  showScoreModal.value = true
}

function handleSaveMatchScore(updated: FixtureMatch) {
  const idx = matches.value.findIndex((m) => m.id === updated.id)
  if (idx >= 0) {
    matches.value[idx] = updated
  }
  showScoreModal.value = false
  showNotice(`Đã lưu kết quả trận đấu #${updated.matchNumber}!`)
}
</script>

<template>
  <div class="admin-tournament-view">
    <!-- Top Header -->
    <header class="page-top-header">
      <div class="header-left-col">
        <h1 class="page-headline">Quản Trị Giải Đấu & Bracket</h1>
        <div class="tournament-subhead">
          <strong class="t-name">{{ tournament.name }}</strong>
          <span class="t-badge">{{ tournament.category }}</span>
          <span class="t-venue">📍 {{ tournament.venue }} ({{ tournament.city }})</span>
        </div>
      </div>

      <div class="header-right-col">
        <div v-if="notice" class="alert-pill">{{ notice }}</div>
        <div v-if="error" class="alert-pill-error">{{ error }}</div>
      </div>
    </header>

    <!-- Sub-navigation Tabs (4 Tabs) -->
    <div class="admin-sub-navigation-bar">
      <button
        class="sub-tab-btn"
        :class="{ active: activeSubNav === 'bracket-management' }"
        @click="activeSubNav = 'bracket-management'"
      >
        🌳 Sơ Đồ Phân Nhánh
      </button>

      <button
        class="sub-tab-btn"
        :class="{ active: activeSubNav === 'athlete-check-in' }"
        @click="activeSubNav = 'athlete-check-in'"
      >
        📋 Điểm Danh VĐV
      </button>

      <button
        class="sub-tab-btn"
        :class="{ active: activeSubNav === 'live-referee-console' }"
        @click="activeSubNav = 'live-referee-console'"
      >
        ⚖️ Bàn Trọng Tài Live
      </button>

      <button
        class="sub-tab-btn"
        :class="{ active: activeSubNav === 'tournament-overview' }"
        @click="activeSubNav = 'tournament-overview'"
      >
        📊 Tổng Quan Giải
      </button>
    </div>

    <!-- Tab 1: Bracket Management -->
    <section v-if="activeSubNav === 'bracket-management'" class="tab-content-section">
      <TournamentSetupWizard
        :tournament="tournament"
        :current-step="currentWizardStep"
        @update:current-step="currentWizardStep = $event"
      />

      <TournamentBracketTree
        :matches="matches"
        :selected-round="selectedRoundFilter"
        :is-generating="isGenerating"
        @update:selected-round="selectedRoundFilter = $event"
        @select-match="handleSelectMatch"
        @regenerate="regenerateBracketTree"
      />
    </section>

    <!-- Tab 2: Athlete Check-in -->
    <section v-else-if="activeSubNav === 'athlete-check-in'" class="tab-content-section">
      <AthleteCheckInPanel
        :athletes="filteredAthletes"
        :filter-status="checkInFilterStatus"
        :search-query="checkInSearchQuery"
        :metrics="checkInMetrics"
        @update:filter-status="checkInFilterStatus = $event"
        @update:search-query="checkInSearchQuery = $event"
        @toggle-check-in="toggleAthleteCheckIn"
      />
    </section>

    <!-- Tab 3: Live Referee Console -->
    <section v-else-if="activeSubNav === 'live-referee-console'" class="tab-content-section">
      <RefereeConsolePanel
        :live-match="liveMatch"
        @increment-a="incrementScoreA"
        @decrement-a="decrementScoreA"
        @increment-b="incrementScoreB"
        @decrement-b="decrementScoreB"
        @switch-ends="switchEnds"
        @simulate-conflict="simulateConflict"
        @resolve-conflict="resolveConflict"
      />
    </section>

    <!-- Tab 4: Overview -->
    <section v-else-if="activeSubNav === 'tournament-overview'" class="tab-content-section">
      <TournamentOverviewPanel :tournament="tournament" />
    </section>

    <!-- Match Details / Score Modal -->
    <ScoreUpdateModal
      :show="showScoreModal"
      :match="selectedMatch"
      @close="showScoreModal = false"
      @save="handleSaveMatchScore"
    />
  </div>
</template>

<style scoped>
.admin-tournament-view {
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-width: 1400px;
  margin: 0 auto;
  padding: 20px;
  color: var(--ink);
}

.page-top-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
  padding-bottom: 14px;
  border-bottom: 1px solid var(--outline);
}

.page-headline {
  margin: 0 0 6px;
  font-size: 22px;
  font-weight: 800;
}

.tournament-subhead {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
  color: var(--muted);
  flex-wrap: wrap;
}

.t-name {
  color: var(--ink);
  font-size: 14px;
}

.t-badge {
  background: rgba(236, 72, 153, 0.1);
  color: var(--pink-dark, #db2777);
  padding: 2px 8px;
  border-radius: 6px;
  font-weight: 700;
}

.admin-sub-navigation-bar {
  display: flex;
  gap: 8px;
  border-bottom: 1px solid var(--outline);
  padding-bottom: 8px;
  overflow-x: auto;
}

.sub-tab-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s;
}

.sub-tab-btn:hover {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
}

.sub-tab-btn.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
}

.alert-pill {
  padding: 6px 14px;
  border-radius: 8px;
  background: #10b981;
  color: #fff;
  font-size: 12px;
  font-weight: 700;
}

.alert-pill-error {
  padding: 6px 14px;
  border-radius: 8px;
  background: #ef4444;
  color: #fff;
  font-size: 12px;
  font-weight: 700;
}

.tab-content-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
</style>

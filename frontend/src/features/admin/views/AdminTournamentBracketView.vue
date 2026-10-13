<script setup lang="ts">
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { FixtureMatch } from '../types/tournamentAdmin'
import { useTournamentBracketAdmin } from '../composables/useTournamentBracketAdmin'
import {
  AuraButton,
  SelectDropdown,
  SkeletonLoader,
  type DropdownOption,
} from '@/shared/components'

import TournamentSetupWizard from '../components/tournament/TournamentSetupWizard.vue'
import TournamentBracketTree from '../components/tournament/TournamentBracketTree.vue'
import AthleteCheckInPanel from '../components/tournament/AthleteCheckInPanel.vue'
import RefereeConsolePanel from '../components/tournament/RefereeConsolePanel.vue'
import TournamentOverviewPanel from '../components/tournament/TournamentOverviewPanel.vue'
import ScoreUpdateModal from '../components/tournament/ScoreUpdateModal.vue'
import TournamentCreateModal from '../components/tournament/TournamentCreateModal.vue'

const { t } = useI18n()

const {
  loading,
  loadingDetails,
  saving,
  tournamentsList,
  selectedTournamentId,
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
  participants,
  liveMatch,
  toggleAthleteCheckIn,
  incrementScoreA,
  decrementScoreA,
  incrementScoreB,
  decrementScoreB,
  switchEnds,
  simulateConflict,
  resolveConflict,
  saveRefereeScore,
  saveMatchScore,
  createTournament,
  regenerateBracketTree,
  selectTournament,
  loadTournaments,
} = useTournamentBracketAdmin()

// Modal State
const selectedMatch = ref<FixtureMatch | null>(null)
const showScoreModal = ref(false)
const showCreateModal = ref(false)

const tournamentOptions = computed<DropdownOption[]>(() => {
  return tournamentsList.value.map((item) => ({
    value: item.id,
    label: item.name,
    sublabel: [item.sport, item.venue].filter(Boolean).join(' · '),
  }))
})

function handleSelectMatch(match: FixtureMatch) {
  selectedMatch.value = match
  showScoreModal.value = true
}

async function handleSaveMatchScore(updated: FixtureMatch, p1Score: number, p2Score: number) {
  const ok = await saveMatchScore(updated, p1Score, p2Score)
  if (ok) {
    showScoreModal.value = false
  }
}

async function handleCreateTournament(payload: any) {
  const ok = await createTournament(payload)
  if (ok) {
    showCreateModal.value = false
  }
}
</script>

<template>
  <div class="admin-tournament-view">
    <!-- Top Header -->
    <header class="page-top-header">
      <div class="header-left-col">
        <div class="header-title-row">
          <span class="material-symbols-outlined header-icon">account_tree</span>
          <h1 class="page-headline">{{ t('admin.tournaments.title') }}</h1>
        </div>

        <div v-if="loading" class="header-skeleton-subhead">
          <SkeletonLoader type="text" width="220px" height="14px" />
        </div>
        <div v-else-if="tournament.id" class="tournament-subhead">
          <strong class="t-name">{{ tournament.name }}</strong>
          <span v-if="tournament.category" class="t-badge">{{ tournament.category }}</span>
          <span v-if="tournament.venue" class="t-venue">
            <span class="material-symbols-outlined icon-14">location_on</span>
            <span>{{ tournament.venue }}{{ tournament.city ? ` (${tournament.city})` : '' }}</span>
          </span>
          <span v-if="tournament.datesFormatted" class="t-dates font-mono">
            <span class="material-symbols-outlined icon-14">calendar_today</span>
            <span>{{ tournament.datesFormatted }}</span>
          </span>
        </div>
      </div>

      <div class="header-right-col">
        <div class="header-controls">
          <!-- Tournament Switcher Dropdown -->
          <div v-if="tournamentsList.length > 0" class="tournament-selector-wrap">
            <SelectDropdown
              :model-value="selectedTournamentId || tournament.id"
              :options="tournamentOptions"
              variant="bordered"
              @update:model-value="selectTournament($event)"
            />
          </div>

          <!-- Action Buttons -->
          <AuraButton variant="secondary" :disabled="loading" @click="loadTournaments">
            <span class="material-symbols-outlined icon-18" :class="{ spin: loading }"
              >refresh</span
            >
            <span>{{ t('admin.tournaments.refreshBtn') }}</span>
          </AuraButton>

          <AuraButton variant="primary" @click="showCreateModal = true">
            <span class="material-symbols-outlined icon-18">add</span>
            <span>{{ t('admin.tournaments.createTournamentBtn') }}</span>
          </AuraButton>
        </div>

        <div v-if="notice" class="alert-pill alert-success">
          <span class="material-symbols-outlined icon-14">check_circle</span>
          <span>{{ notice }}</span>
        </div>
        <div v-if="error" class="alert-pill alert-error">
          <span class="material-symbols-outlined icon-14">error</span>
          <span>{{ error }}</span>
        </div>
      </div>
    </header>

    <!-- Sub-navigation Tabs (4 Tabs) -->
    <div class="admin-sub-navigation-bar">
      <button
        type="button"
        class="sub-tab-btn"
        :class="{ active: activeSubNav === 'bracket-management' }"
        @click="activeSubNav = 'bracket-management'"
      >
        <span class="material-symbols-outlined icon-18">account_tree</span>
        <span>{{ t('admin.tournaments.tabBracket') }}</span>
      </button>

      <button
        type="button"
        class="sub-tab-btn"
        :class="{ active: activeSubNav === 'athlete-check-in' }"
        @click="activeSubNav = 'athlete-check-in'"
      >
        <span class="material-symbols-outlined icon-18">checklist</span>
        <span>{{ t('admin.tournaments.tabCheckIn') }}</span>
        <span v-if="participants.length > 0" class="tab-badge font-mono"
          >{{ checkInMetrics.checkedInCount }}/{{ checkInMetrics.total }}</span
        >
      </button>

      <button
        type="button"
        class="sub-tab-btn"
        :class="{ active: activeSubNav === 'live-referee-console' }"
        @click="activeSubNav = 'live-referee-console'"
      >
        <span class="material-symbols-outlined icon-18">scoreboard</span>
        <span>{{ t('admin.tournaments.tabReferee') }}</span>
      </button>

      <button
        type="button"
        class="sub-tab-btn"
        :class="{ active: activeSubNav === 'tournament-overview' }"
        @click="activeSubNav = 'tournament-overview'"
      >
        <span class="material-symbols-outlined icon-18">analytics</span>
        <span>{{ t('admin.tournaments.tabOverview') }}</span>
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
        :loading="loadingDetails"
        @update:selected-round="selectedRoundFilter = $event"
        @select-match="handleSelectMatch"
        @regenerate="regenerateBracketTree"
      />
    </section>

    <!-- Tab 2: Athlete Check-in -->
    <section v-else-if="activeSubNav === 'athlete-check-in'" class="tab-content-section">
      <AthleteCheckInPanel
        :athletes="filteredAthletes"
        :tournament-name="tournament.name"
        :filter-status="checkInFilterStatus"
        :search-query="checkInSearchQuery"
        :metrics="checkInMetrics"
        :loading="loadingDetails"
        @update:filter-status="checkInFilterStatus = $event"
        @update:search-query="checkInSearchQuery = $event"
        @toggle-check-in="toggleAthleteCheckIn"
      />
    </section>

    <!-- Tab 3: Live Referee Console -->
    <section v-else-if="activeSubNav === 'live-referee-console'" class="tab-content-section">
      <RefereeConsolePanel
        :live-match="liveMatch"
        :saving="saving"
        @increment-a="incrementScoreA"
        @decrement-a="decrementScoreA"
        @increment-b="incrementScoreB"
        @decrement-b="decrementScoreB"
        @switch-ends="switchEnds"
        @simulate-conflict="simulateConflict"
        @resolve-conflict="resolveConflict"
        @save-score="saveRefereeScore"
      />
    </section>

    <!-- Tab 4: Overview -->
    <section v-else-if="activeSubNav === 'tournament-overview'" class="tab-content-section">
      <TournamentOverviewPanel
        :tournament="tournament"
        :participants="participants"
        :matches="matches"
        :loading="loadingDetails"
      />
    </section>

    <!-- Match Details / Score Modal -->
    <ScoreUpdateModal
      :show="showScoreModal"
      :match="selectedMatch"
      :saving="saving"
      @close="showScoreModal = false"
      @save="handleSaveMatchScore"
    />

    <!-- Create Tournament Modal -->
    <TournamentCreateModal
      :show="showCreateModal"
      :saving="saving"
      @close="showCreateModal = false"
      @create="handleCreateTournament"
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
  color: var(--ink, #0f172a);
}

.page-top-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 16px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--outline, #e2e8f0);
}

.header-left-col {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.header-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-icon {
  font-size: 26px;
  color: var(--pink-dark, #db2777);
}

.page-headline {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
  color: var(--ink, #0f172a);
}

.tournament-subhead {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
  color: var(--muted, #64748b);
  flex-wrap: wrap;
}

.t-name {
  color: var(--ink, #0f172a);
  font-size: 14px;
}

.t-badge {
  background: rgba(236, 72, 153, 0.1);
  color: var(--pink-dark, #db2777);
  padding: 2px 8px;
  border-radius: 6px;
  font-weight: 700;
}

.t-venue,
.t-dates {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.icon-14 {
  font-size: 14px;
}

.header-right-col {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
}

.header-controls {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.tournament-selector-wrap {
  min-width: 240px;
}

.alert-pill {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
}

.alert-success {
  background: #10b981;
  color: #fff;
}

.alert-error {
  background: #ef4444;
  color: #fff;
}

.admin-sub-navigation-bar {
  display: flex;
  gap: 8px;
  border-bottom: 1px solid var(--outline, #e2e8f0);
  padding-bottom: 8px;
  overflow-x: auto;
}

.sub-tab-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 12px;
  border: 1px solid var(--outline, #e2e8f0);
  background: var(--surface, #ffffff);
  color: var(--ink, #0f172a);
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

.tab-badge {
  background: rgba(0, 0, 0, 0.1);
  padding: 2px 6px;
  border-radius: 6px;
  font-size: 11px;
}

.sub-tab-btn.active .tab-badge {
  background: rgba(255, 255, 255, 0.25);
  color: #fff;
}

.tab-content-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
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

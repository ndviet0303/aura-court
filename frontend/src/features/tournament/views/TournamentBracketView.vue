<script setup lang="ts">
import { useTournamentBracket } from '../composables/useTournamentBracket'
import { SkeletonLoader } from '@/shared/components'

import TournamentBracketHeader from '../components/bracket/TournamentBracketHeader.vue'
import TournamentPublicBracketTree from '../components/bracket/TournamentPublicBracketTree.vue'
import TournamentGroupStandingsTable from '../components/bracket/TournamentGroupStandingsTable.vue'
import TournamentMatchDetailModal from '../components/bracket/TournamentMatchDetailModal.vue'

const {
  loading,
  detailLoading,
  currentTournamentId,
  selectedCategory,
  activeTab,
  selectedMatch,
  isMatchDetailModalOpen,
  tournaments,
  currentTournament,
  quarterMatches,
  semiMatches,
  finalMatch,
  groupStandings,
  openMatchDetail,
  selectTournament,
} = useTournamentBracket()
</script>

<template>
  <div class="tournament-bracket-view">
    <!-- Header Section -->
    <TournamentBracketHeader
      :tournament="currentTournament"
      :tournaments="tournaments"
      :selected-tournament-id="currentTournamentId"
      :selected-category="selectedCategory"
      :active-tab="activeTab"
      @update:selected-tournament-id="selectTournament($event)"
      @update:selected-category="selectedCategory = $event"
      @update:active-tab="activeTab = $event"
    />

    <!-- Main Content Area -->
    <main class="bracket-main-body">
      <!-- Loading Skeleton State -->
      <div v-if="loading || detailLoading" class="bracket-skeleton-wrapper">
        <div class="bracket-skeleton-grid">
          <div v-for="col in 3" :key="col" class="bracket-skeleton-col">
            <div class="col-head-skeleton">
              <SkeletonLoader type="rect" width="140px" height="22px" rounded="6px" />
            </div>
            <div class="skeleton-matches-stack">
              <div v-for="m in (col === 1 ? 4 : col === 2 ? 2 : 1)" :key="m" class="skeleton-match-card">
                <div class="skeleton-meta-line">
                  <SkeletonLoader type="rect" width="100px" height="12px" rounded="4px" />
                  <SkeletonLoader type="rect" width="48px" height="12px" rounded="4px" />
                </div>
                <div class="skeleton-p-line">
                  <div class="p-left">
                    <SkeletonLoader type="rect" width="26px" height="26px" rounded="50%" />
                    <SkeletonLoader type="rect" width="110px" height="14px" rounded="4px" />
                  </div>
                  <SkeletonLoader type="rect" width="22px" height="18px" rounded="4px" />
                </div>
                <div class="skeleton-p-line">
                  <div class="p-left">
                    <SkeletonLoader type="rect" width="26px" height="26px" rounded="50%" />
                    <SkeletonLoader type="rect" width="95px" height="14px" rounded="4px" />
                  </div>
                  <SkeletonLoader type="rect" width="22px" height="18px" rounded="4px" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Knockout Tree View -->
      <TournamentPublicBracketTree
        v-else-if="activeTab === 'bracket'"
        :quarters="quarterMatches"
        :semis="semiMatches"
        :final-match="finalMatch"
        @select-match="openMatchDetail"
      />

      <!-- Group Standings View -->
      <TournamentGroupStandingsTable
        v-else
        :standings="groupStandings"
      />
    </main>

    <!-- Match Detail Modal -->
    <TournamentMatchDetailModal
      :show="isMatchDetailModalOpen"
      :match="selectedMatch"
      :tournament-id="currentTournament?.uuid || currentTournamentId"
      @close="isMatchDetailModalOpen = false"
    />
  </div>
</template>

<style scoped>
.tournament-bracket-view {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px 20px 48px;
  color: var(--ink);
}

.bracket-main-body {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.bracket-skeleton-wrapper {
  overflow-x: auto;
  padding: 8px 0;
}

.bracket-skeleton-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
  min-width: 860px;
}

.bracket-skeleton-col {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.col-head-skeleton {
  padding-bottom: 8px;
  border-bottom: 1px solid var(--outline);
}

.skeleton-matches-stack {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.skeleton-match-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px;
  border-radius: 14px;
  background: var(--surface);
  border: 1px solid var(--outline);
}

.skeleton-meta-line {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--outline);
}

.skeleton-p-line {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
}

.p-left {
  display: flex;
  align-items: center;
  gap: 8px;
}
</style>

<script setup lang="ts">
import { useTournamentBracket } from '../composables/useTournamentBracket'

import TournamentBracketHeader from '../components/bracket/TournamentBracketHeader.vue'
import TournamentPublicBracketTree from '../components/bracket/TournamentPublicBracketTree.vue'
import TournamentGroupStandingsTable from '../components/bracket/TournamentGroupStandingsTable.vue'
import TournamentMatchDetailModal from '../components/bracket/TournamentMatchDetailModal.vue'

const {
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
      @update:selected-tournament-id="currentTournamentId = $event"
      @update:selected-category="selectedCategory = $event"
      @update:active-tab="activeTab = $event"
    />

    <!-- Main Content Area -->
    <main class="bracket-main-body">
      <!-- Knockout Tree View -->
      <TournamentPublicBracketTree
        v-if="activeTab === 'bracket'"
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
  padding: 24px 20px;
  color: var(--ink);
}

.bracket-main-body {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
</style>

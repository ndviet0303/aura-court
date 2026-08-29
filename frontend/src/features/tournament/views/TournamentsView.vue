<script setup lang="ts">
import { useTournamentsList } from '../composables/useTournamentsList'
import type { TournamentItem } from '../types/tournament'

import TournamentCard from '../components/TournamentCard.vue'
import TournamentFilters from '../components/TournamentFilters.vue'
import TournamentRegisterModal from '../components/TournamentRegisterModal.vue'
import TournamentQuickBracketModal from '../components/TournamentQuickBracketModal.vue'
import CircuitLeaderboardCard from '../components/CircuitLeaderboardCard.vue'

const {
  activeTab,
  searchQuery,
  selectedSport,
  selectedFormat,
  loading,
  notice,
  error,
  selectedTournamentForRegister,
  selectedTournamentForBracket,
  circuitLeaderboard,
  filteredTournaments,
  registerAthlete,
} = useTournamentsList()
</script>

<template>
  <div class="tournaments-view-page">
    <!-- Hero Banner -->
    <section class="tournaments-hero-banner">
      <div class="hero-content">
        <span class="hero-tag">AURA TOURNAMENT CIRCUIT 2026</span>
        <h1 class="hero-title">🏆 Hệ Thống Giải Đấu & Tranh Tài Chuyên Nghiệp</h1>
        <p class="hero-sub">
          Đăng ký tham gia tranh tài các giải đấu cầu lông, pickleball đẳng cấp. Cập nhật nhánh đấu trực tiếp và tính điểm xếp hạng VĐV.
        </p>
      </div>
    </section>

    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill">{{ notice }}</div>
    <div v-if="error" class="alert-pill-error">{{ error }}</div>

    <!-- Circuit Leaderboard -->
    <CircuitLeaderboardCard :leaderboard="circuitLeaderboard" />

    <!-- Filters Toolbar -->
    <TournamentFilters
      :active-tab="activeTab"
      :search-query="searchQuery"
      :selected-sport="selectedSport"
      :selected-format="selectedFormat"
      @update:active-tab="activeTab = $event"
      @update:search-query="searchQuery = $event"
      @update:selected-sport="selectedSport = $event"
      @update:selected-format="selectedFormat = $event"
    />

    <!-- Tournament Cards Grid -->
    <div v-if="filteredTournaments.length === 0" class="empty-tournaments-state">
      <span class="empty-ico">🏸</span>
      <h3>Không tìm thấy giải đấu nào</h3>
      <p>Thử tìm kiếm với từ khóa khác hoặc chuyển sang tab khác.</p>
    </div>

    <div v-else class="tournaments-grid">
      <TournamentCard
        v-for="t in filteredTournaments"
        :key="t.id"
        :tournament="t"
        @register="selectedTournamentForRegister = $event"
        @view-bracket="selectedTournamentForBracket = $event"
      />
    </div>

    <!-- Modals -->
    <TournamentRegisterModal
      :show="!!selectedTournamentForRegister"
      :tournament="selectedTournamentForRegister"
      @close="selectedTournamentForRegister = null"
      @submit="registerAthlete"
    />

    <TournamentQuickBracketModal
      :show="!!selectedTournamentForBracket"
      :tournament="selectedTournamentForBracket"
      @close="selectedTournamentForBracket = null"
    />
  </div>
</template>

<style scoped>
.tournaments-view-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.tournaments-hero-banner {
  padding: 40px 30px;
  border-radius: 20px;
  background: linear-gradient(135deg, rgba(236, 72, 153, 0.15) 0%, rgba(139, 92, 246, 0.1) 100%);
  border: 1px solid rgba(236, 72, 153, 0.2);
}

.hero-tag {
  font-size: 11px;
  font-weight: 800;
  color: var(--pink-dark, #db2777);
  letter-spacing: 1px;
}

.hero-title {
  margin: 8px 0 10px;
  font-size: 26px;
  font-weight: 900;
}

.hero-sub {
  margin: 0;
  font-size: 14px;
  color: var(--muted);
  max-width: 700px;
  line-height: 1.5;
}

.alert-pill {
  padding: 8px 16px;
  border-radius: 8px;
  background: #10b981;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
}

.alert-pill-error {
  padding: 8px 16px;
  border-radius: 8px;
  background: #ef4444;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
}

.tournaments-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 20px;
}

.empty-tournaments-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 60px 20px;
  text-align: center;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
}

.empty-ico {
  font-size: 48px;
  margin-bottom: 12px;
}
</style>

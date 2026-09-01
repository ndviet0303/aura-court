<script setup lang="ts">
import { useTournamentsList } from '../composables/useTournamentsList'
import { SkeletonLoader, AuraButton } from '@/shared/components'

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
  processing,
  notice,
  error,
  stats,
  selectedTournamentForRegister,
  selectedTournamentForBracket,
  circuitLeaderboard,
  filteredTournaments,
  resetFilters,
  registerAthlete,
} = useTournamentsList()
</script>

<template>
  <main class="tournaments-view-page">
    <!-- Hero Banner with Stats -->
    <header class="tournaments-hero-banner">
      <div class="hero-content">
        <span class="hero-tag">AURA TOURNAMENT CIRCUIT 2026</span>
        <h1 class="hero-title">Hệ Thống Giải Đấu & Tranh Tài Cao Cấp</h1>
        <p class="hero-sub">
          Đăng ký tham gia tranh tài các giải đấu Cầu lông, Pickleball, Tennis chuẩn chuyên nghiệp. Theo dõi nhánh đấu, bảng xếp hạng và tỷ số thời gian thực.
        </p>
      </div>

      <div class="hero-stats-row">
        <div class="hero-stat-card">
          <span class="stat-lbl">
            <span class="material-symbols-outlined stat-ico">emoji_events</span>
            Tổng Giải Đấu
          </span>
          <SkeletonLoader v-if="loading" type="rect" width="48px" height="28px" rounded="6px" />
          <strong v-else class="stat-val font-mono">{{ stats.total }}</strong>
        </div>

        <div class="hero-stat-card highlight">
          <span class="stat-lbl">
            <span class="material-symbols-outlined stat-ico">how_to_reg</span>
            Đang Mở Đăng Ký
          </span>
          <SkeletonLoader v-if="loading" type="rect" width="48px" height="28px" rounded="6px" />
          <strong v-else class="stat-val font-mono">{{ stats.open }}</strong>
        </div>

        <div class="hero-stat-card gold">
          <span class="stat-lbl">
            <span class="material-symbols-outlined stat-ico">payments</span>
            Tổng Quỹ Thưởng
          </span>
          <SkeletonLoader v-if="loading" type="rect" width="100px" height="28px" rounded="6px" />
          <strong v-else class="stat-val font-mono">{{ stats.prizePoolTotal }}</strong>
        </div>
      </div>
    </header>


    <!-- Feedback Notice Alerts -->
    <transition name="fade">
      <div v-if="notice" class="alert-pill-success" role="status">
        <span class="material-symbols-outlined alert-ico">check_circle</span>
        <span>{{ notice }}</span>
      </div>
    </transition>

    <transition name="fade">
      <div v-if="error" class="alert-pill-error" role="alert">
        <span class="material-symbols-outlined alert-ico">error</span>
        <span>{{ error }}</span>
      </div>
    </transition>

    <!-- Circuit Leaderboard (when API data exists) -->
    <CircuitLeaderboardCard v-if="circuitLeaderboard && circuitLeaderboard.length > 0" :leaderboard="circuitLeaderboard" />


    <!-- Filters Toolbar -->
    <TournamentFilters
      :active-tab="activeTab"
      :search-query="searchQuery"
      :selected-sport="selectedSport"
      :selected-format="selectedFormat"
      :open-count="stats.open"
      :ongoing-count="stats.ongoing"
      :total-count="stats.total"
      @update:active-tab="activeTab = $event"
      @update:search-query="searchQuery = $event"
      @update:selected-sport="selectedSport = $event"
      @update:selected-format="selectedFormat = $event"
      @reset="resetFilters"
    />

    <!-- Skeleton Loading State -->
    <div v-if="loading" class="tournaments-grid">
      <div v-for="i in 6" :key="i" class="tournament-skeleton-card">
        <SkeletonLoader type="rect" width="100%" height="190px" rounded="18px 18px 0 0" />
        <div class="skeleton-body">
          <SkeletonLoader type="rect" width="70%" height="20px" rounded="6px" />
          <SkeletonLoader type="rect" width="90%" height="14px" rounded="4px" />
          <SkeletonLoader type="rect" width="100%" height="14px" rounded="4px" />
          <div class="skeleton-bottom">
            <SkeletonLoader type="rect" width="45%" height="40px" rounded="10px" />
            <SkeletonLoader type="rect" width="45%" height="40px" rounded="10px" />
          </div>
        </div>
      </div>
    </div>

    <!-- Empty Tournaments State -->
    <div v-else-if="filteredTournaments.length === 0" class="empty-tournaments-state">
      <div class="empty-icon-circle">
        <span class="material-symbols-outlined">sports_tennis</span>
      </div>
      <h3 class="empty-title">Không tìm thấy giải đấu nào phù hợp</h3>
      <p class="empty-sub">Hãy thử đổi từ khóa tìm kiếm hoặc đặt lại các bộ lọc thể thức và bộ môn.</p>
      <AuraButton variant="secondary" size="md" icon="restart_alt" @click="resetFilters">
        Đặt lại bộ lọc
      </AuraButton>
    </div>

    <!-- Tournaments Cards Grid -->
    <div v-else class="tournaments-grid">
      <TournamentCard
        v-for="t in filteredTournaments"
        :key="t.id"
        :tournament="t"
        @register="selectedTournamentForRegister = $event"
        @view-bracket="selectedTournamentForBracket = $event"
      />
    </div>

    <!-- Registration Modal -->
    <TournamentRegisterModal
      :show="!!selectedTournamentForRegister"
      :tournament="selectedTournamentForRegister"
      :loading="processing"
      @close="selectedTournamentForRegister = null"
      @submit="registerAthlete"
    />

    <!-- Quick Mini Bracket Preview Modal -->
    <TournamentQuickBracketModal
      :show="!!selectedTournamentForBracket"
      :tournament="selectedTournamentForBracket"
      @close="selectedTournamentForBracket = null"
    />
  </main>
</template>

<style scoped>
.tournaments-view-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px 20px 48px;
  color: var(--ink);
}

.tournaments-hero-banner {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 24px;
  padding: 36px 32px;
  border-radius: 24px;
  background: linear-gradient(135deg, rgba(107, 45, 63, 0.18) 0%, rgba(236, 72, 153, 0.12) 50%, rgba(201, 161, 90, 0.08) 100%);
  border: 1px solid var(--outline);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
}

:global(html.dark .tournaments-hero-banner) {
  background: linear-gradient(135deg, rgba(229, 142, 159, 0.12) 0%, rgba(107, 45, 63, 0.25) 50%, rgba(201, 161, 90, 0.05) 100%);
}

.hero-content {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-width: 680px;
}

.hero-tag {
  font-size: 11px;
  font-weight: 800;
  color: var(--pink-dark, #db2777);
  letter-spacing: 1.2px;
  text-transform: uppercase;
}

.hero-title {
  margin: 0;
  font-size: 26px;
  font-weight: 900;
  line-height: 1.25;
  color: var(--ink);
}

.hero-sub {
  margin: 0;
  font-size: 13.5px;
  color: var(--muted);
  line-height: 1.5;
}

.hero-cta-actions {
  display: flex;
  gap: 10px;
  margin-top: 4px;
}

.hero-stats-row {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.hero-stat-card {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px 18px;
  border-radius: 16px;
  background: var(--surface);
  border: 1px solid var(--outline);
  min-width: 140px;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
}

.hero-stat-card.highlight {
  border-color: rgba(16, 185, 129, 0.35);
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.08) 0%, var(--surface) 100%);
}

.hero-stat-card.gold {
  border-color: rgba(201, 161, 90, 0.35);
  background: linear-gradient(135deg, rgba(201, 161, 90, 0.08) 0%, var(--surface) 100%);
}

.stat-lbl {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  font-weight: 650;
  color: var(--muted);
}

.stat-ico {
  font-size: 15px;
  color: var(--pink-dark, #db2777);
}

.stat-val {
  font-size: 18px;
  font-weight: 850;
  color: var(--ink);
}

.alert-pill-success, .alert-pill-error {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 650;
}

.alert-pill-success {
  background: rgba(16, 185, 129, 0.12);
  color: #065f46;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

:global(html.dark .alert-pill-success) {
  color: #6ee7b7;
}

.alert-pill-error {
  background: rgba(239, 68, 68, 0.12);
  color: #991b1b;
  border: 1px solid rgba(239, 68, 68, 0.3);
}

:global(html.dark .alert-pill-error) {
  color: #fca5a5;
}

.alert-ico {
  font-size: 18px;
}

.tournaments-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 24px;
}

.tournament-skeleton-card {
  border-radius: 18px;
  border: 1px solid var(--outline);
  background: var(--surface);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.skeleton-body {
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.skeleton-bottom {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-top: 8px;
}

.empty-tournaments-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 64px 20px;
  text-align: center;
  background: var(--surface);
  border-radius: 20px;
  border: 1px solid var(--outline);
  gap: 12px;
}

.empty-icon-circle {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: rgba(236, 72, 153, 0.1);
  color: var(--pink-dark, #db2777);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 4px;
}

.empty-icon-circle .material-symbols-outlined {
  font-size: 32px;
}

.empty-title {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
}

.empty-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
  max-width: 440px;
  line-height: 1.4;
}

@media (max-width: 850px) {
  .tournaments-hero-banner {
    flex-direction: column;
    align-items: flex-start;
  }
  .hero-stats-row {
    width: 100%;
  }
  .hero-stat-card {
    flex: 1;
  }
}
</style>


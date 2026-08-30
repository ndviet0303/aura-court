<script setup lang="ts">
import { useSocialTournament } from '../composables/useSocialTournament'

import SocialMatchCard from '../components/social/SocialMatchCard.vue'
import SocialJoinModal from '../components/social/SocialJoinModal.vue'
import SocialPartnerFinderModal from '../components/social/SocialPartnerFinderModal.vue'

const {
  activeTab,
  searchQuery,
  notice,
  error,
  selectedMatchForJoin,
  showPartnerFinder,
  filteredMatches,
  joinMatch,
} = useSocialTournament()
</script>

<template>
  <div class="social-tournament-view">
    <!-- Header Hero -->
    <header class="social-header">
      <div class="header-titles">
        <h1 class="page-title">Ghép Kèo Giao Lưu & Tìm Bạn Thi Đấu</h1>
        <p class="page-sub">Tìm kiếm bạn chơi thể thao cùng trình độ, tạo kèo thi đấu giao lưu hoặc tìm đồng đội đánh giải.</p>
      </div>

      <div class="header-actions">
        <button class="btn-partner-finder" @click="showPartnerFinder = true">
          🤝 Tìm Bạn Ghép Đôi
        </button>
      </div>
    </header>

    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill">{{ notice }}</div>
    <div v-if="error" class="alert-pill-error">{{ error }}</div>

    <!-- Search Toolbar -->
    <div class="social-toolbar">
      <input
        v-model="searchQuery"
        type="text"
        class="search-input"
        placeholder="Tìm kèo theo tên phòng, cơ sở, người tạo..."
      />
    </div>

    <!-- Matches Grid -->
    <div v-if="filteredMatches.length === 0" class="empty-matches-box">
      <h3>Không tìm thấy kèo giao lưu phù hợp</h3>
      <p>Thử tìm kiếm với từ khóa khác hoặc tạo kèo mới cho riêng bạn.</p>
    </div>

    <div v-else class="matches-grid">
      <SocialMatchCard
        v-for="m in filteredMatches"
        :key="m.id"
        :match="m"
        @join="selectedMatchForJoin = $event"
      />
    </div>

    <!-- Modals -->
    <SocialJoinModal
      :show="!!selectedMatchForJoin"
      :match="selectedMatchForJoin"
      @close="selectedMatchForJoin = null"
      @confirm="joinMatch"
    />

    <SocialPartnerFinderModal
      :show="showPartnerFinder"
      @close="showPartnerFinder = false"
    />
  </div>
</template>

<style scoped>
.social-tournament-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1200px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.social-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
}

.page-title {
  margin: 0 0 6px;
  font-size: 22px;
  font-weight: 800;
}

.page-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.btn-partner-finder {
  padding: 10px 18px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
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

.social-toolbar {
  display: flex;
}

.search-input {
  width: 100%;
  padding: 10px 16px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
}

.matches-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 16px;
}

.empty-matches-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 60px 20px;
  text-align: center;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
}

.empty-matches-box span {
  font-size: 48px;
  margin-bottom: 12px;
}
</style>

<script setup lang="ts">
import { useSocialTournament } from '../composables/useSocialTournament'
import { AuraButton, SkeletonLoader } from '@/shared/components'

import SocialMatchCard from '../components/social/SocialMatchCard.vue'
import SocialJoinModal from '../components/social/SocialJoinModal.vue'
import SocialPartnerFinderModal from '../components/social/SocialPartnerFinderModal.vue'

const {
  loading,
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
  <main class="social-tournament-view">
    <!-- Header Hero -->
    <header class="social-header">
      <div class="header-titles">
        <div class="top-nav">
          <AuraButton to="/tournaments" variant="ghost" size="sm" icon="arrow_back">
            Quay lại Giải Đấu
          </AuraButton>
        </div>
        <h1 class="page-title">Ghép Kèo Giao Lưu & Tìm Bạn Thi Đấu</h1>
        <p class="page-sub">
          Tìm kiếm bạn chơi thể thao cùng trình độ, tạo kèo thi đấu giao lưu hoặc tìm đồng đội đánh
          giải.
        </p>
      </div>

      <div class="header-actions">
        <AuraButton variant="primary" size="md" icon="group_add" @click="showPartnerFinder = true">
          Tìm Bạn Ghép Đôi
        </AuraButton>
      </div>
    </header>

    <!-- Feedback Alerts -->
    <transition name="fade">
      <div v-if="notice" class="alert-pill-success">
        <span class="material-symbols-outlined alert-ico">check_circle</span>
        <span>{{ notice }}</span>
      </div>
    </transition>

    <transition name="fade">
      <div v-if="error" class="alert-pill-error">
        <span class="material-symbols-outlined alert-ico">error</span>
        <span>{{ error }}</span>
      </div>
    </transition>

    <!-- Search Toolbar -->
    <div class="social-toolbar">
      <div class="search-input-wrap">
        <span class="material-symbols-outlined search-ico">search</span>
        <input
          v-model="searchQuery"
          type="text"
          class="search-input"
          placeholder="Tìm kèo theo tên phòng, cơ sở, người tạo..."
        />
      </div>
    </div>

    <!-- Skeleton Loading Grid -->
    <div v-if="loading" class="matches-grid">
      <div v-for="i in 6" :key="i" class="social-skeleton-card">
        <div class="sk-top-row">
          <SkeletonLoader type="rect" width="38px" height="38px" rounded="10px" />
          <div class="sk-title-col">
            <SkeletonLoader type="rect" width="80%" height="16px" rounded="4px" />
            <SkeletonLoader type="rect" width="50%" height="12px" rounded="4px" />
          </div>
          <SkeletonLoader type="rect" width="70px" height="22px" rounded="999px" />
        </div>
        <div class="sk-meta-lines">
          <SkeletonLoader type="rect" width="75%" height="13px" rounded="4px" />
          <SkeletonLoader type="rect" width="55%" height="13px" rounded="4px" />
          <SkeletonLoader type="rect" width="65%" height="13px" rounded="4px" />
        </div>
        <SkeletonLoader type="rect" width="100%" height="40px" rounded="10px" />
      </div>
    </div>

    <!-- Matches Grid -->
    <div v-else-if="filteredMatches.length === 0" class="empty-matches-box">
      <div class="empty-icon-circle">
        <span class="material-symbols-outlined">group</span>
      </div>
      <h3 class="empty-title">Không tìm thấy kèo giao lưu phù hợp</h3>
      <p class="empty-sub">Thử tìm kiếm với từ khóa khác hoặc tạo kèo mới cho riêng bạn.</p>
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

    <SocialPartnerFinderModal :show="showPartnerFinder" @close="showPartnerFinder = false" />
  </main>
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
  width: 100%;
}

.search-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
  width: 100%;
}

.search-ico {
  position: absolute;
  left: 14px;
  color: var(--muted);
  font-size: 18px;
  pointer-events: none;
}

.search-input {
  width: 100%;
  padding: 12px 16px 12px 42px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13.5px;
  outline: none;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease;
}

.search-input:focus {
  border-color: var(--primary, #db2777);
  box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.15);
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

.social-skeleton-card {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 18px;
  border-radius: 16px;
  background: var(--surface);
  border: 1px solid var(--outline);
}

.sk-top-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.sk-title-col {
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1;
}

.sk-meta-lines {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 10px 0;
}
</style>

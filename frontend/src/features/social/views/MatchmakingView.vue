<script setup lang="ts">
import { useMatchmaking } from '../composables/useMatchmaking'
import { SkeletonLoader, AuraButton } from '@/shared/components'

import MatchmakingHero from '../components/MatchmakingHero.vue'
import MatchmakingFilters from '../components/MatchmakingFilters.vue'
import MatchCard from '../components/MatchCard.vue'
import MatchJoinModal from '../components/MatchJoinModal.vue'
import PartnerFinderList from '../components/PartnerFinderList.vue'

const {
  loading,
  processing,
  activeTab,
  searchQuery,
  selectedSport,
  selectedSkill,
  selectedCity,
  notice,
  error,
  selectedMatchForJoin,
  matches,
  partners,
  stats,
  filteredMatches,
  myMatches,
  joinMatch,
  resetFilters,
  showNotice,
} = useMatchmaking()
</script>

<template>
  <main class="matchmaking-page-view">
    <!-- Hero Banner with Stats -->
    <MatchmakingHero
      :stats="stats"
      :loading="loading"
      @switch-tab="activeTab = $event"
    />

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

    <!-- Filters & Tabs Toolbar -->
    <MatchmakingFilters
      :active-tab="activeTab"
      :search-query="searchQuery"
      :selected-sport="selectedSport"
      :selected-skill="selectedSkill"
      :selected-city="selectedCity"
      :total-count="matches.length"
      :open-count="stats.openMatches"
      :my-matches-count="myMatches.length"
      :partners-count="partners.length"
      @update:active-tab="activeTab = $event"
      @update:search-query="searchQuery = $event"
      @update:selected-sport="selectedSport = $event"
      @update:selected-skill="selectedSkill = $event"
      @update:selected-city="selectedCity = $event"
      @reset="resetFilters"
    />

    <!-- Tab 1 & 2 & 3: Matches Grid -->
    <section v-if="activeTab !== 'partners'" class="matches-section">
      <!-- Skeleton Loading State -->
      <div v-if="loading" class="matches-grid">
        <div v-for="i in 6" :key="i" class="match-skeleton-card">
          <div class="sk-top">
            <SkeletonLoader type="rect" width="90px" height="22px" rounded="8px" />
            <SkeletonLoader type="rect" width="80px" height="22px" rounded="999px" />
          </div>
          <SkeletonLoader type="rect" width="85%" height="20px" rounded="6px" />
          <div class="sk-host">
            <SkeletonLoader type="rect" width="36px" height="36px" rounded="10px" />
            <div class="sk-host-txt">
              <SkeletonLoader type="rect" width="100px" height="14px" rounded="4px" />
              <SkeletonLoader type="rect" width="70px" height="10px" rounded="4px" />
            </div>
          </div>
          <SkeletonLoader type="rect" width="100%" height="70px" rounded="12px" />
          <SkeletonLoader type="rect" width="100%" height="40px" rounded="12px" />
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredMatches.length === 0" class="empty-matches-box">
        <div class="empty-icon-circle">
          <span class="material-symbols-outlined">group_off</span>
        </div>
        <h3 class="empty-title">Không tìm thấy kèo giao lưu phù hợp</h3>
        <p class="empty-sub">Hãy thử chọn môn thể thao khác hoặc tự mở kèo để mời bạn bè tham gia.</p>
        <div class="empty-actions">
          <AuraButton variant="secondary" size="md" icon="restart_alt" @click="resetFilters">
            Xóa Bộ Lọc
          </AuraButton>
          <AuraButton to="/matchmaking/create" variant="primary" size="md" icon="add_circle">
            Tạo Kèo Mới
          </AuraButton>
        </div>
      </div>

      <!-- Real Matches Grid -->
      <div v-else class="matches-grid">
        <MatchCard
          v-for="m in filteredMatches"
          :key="m.id"
          :match="m"
          @join="selectedMatchForJoin = $event"
        />
      </div>
    </section>

    <!-- Tab 4: Partner Finder -->
    <PartnerFinderList
      v-else
      :partners="partners"
      @connect="showNotice(`Đã gửi lời mời giao lưu đến ${$event.name}!`)"
    />

    <!-- Join Match Modal -->
    <MatchJoinModal
      :show="!!selectedMatchForJoin"
      :match="selectedMatchForJoin"
      :processing="processing"
      @close="selectedMatchForJoin = null"
      @confirm="joinMatch"
    />
  </main>
</template>

<style scoped>
.matchmaking-page-view {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1200px;
  margin: 0 auto;
  padding: 24px 20px 48px;
  color: var(--ink);
}

.alert-pill-success {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 12px;
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.3);
  font-size: 13.5px;
  font-weight: 700;
}

.alert-pill-error {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 12px;
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
  border: 1px solid rgba(239, 68, 68, 0.3);
  font-size: 13.5px;
  font-weight: 700;
}

.alert-ico {
  font-size: 20px;
  flex-shrink: 0;
}

.matches-section {
  display: flex;
  flex-direction: column;
}

.matches-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 18px;
}

.match-skeleton-card {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 18px;
  border-radius: 18px;
  background: var(--surface);
  border: 1px solid var(--outline);
}

.sk-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.sk-host {
  display: flex;
  align-items: center;
  gap: 10px;
}

.sk-host-txt {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.empty-matches-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 56px 20px;
  text-align: center;
  background: var(--surface);
  border-radius: 20px;
  border: 1px solid var(--outline);
}

.empty-icon-circle {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: var(--surface-container, rgba(0, 0, 0, 0.04));
  color: var(--muted);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 14px;
}

.empty-icon-circle span {
  font-size: 32px;
}

.empty-title {
  margin: 0 0 6px;
  font-size: 17px;
  font-weight: 800;
  color: var(--ink);
}

.empty-sub {
  margin: 0 0 20px;
  font-size: 13px;
  color: var(--muted);
  max-width: 420px;
}

.empty-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

@media (max-width: 768px) {
  .matches-grid {
    grid-template-columns: 1fr;
  }
}
</style>

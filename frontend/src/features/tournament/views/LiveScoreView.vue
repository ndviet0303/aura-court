<script setup lang="ts">
import { useLiveScore } from '../composables/useLiveScore'
import { AuraButton, SkeletonLoader } from '@/shared/components'

import LiveFixturesSelector from '../components/live/LiveFixturesSelector.vue'
import LiveMatchScoreboard from '../components/live/LiveMatchScoreboard.vue'
import LiveMatchStatsCard from '../components/live/LiveMatchStatsCard.vue'
import LivePlayByPlayTicker from '../components/live/LivePlayByPlayTicker.vue'
import LiveUmpireControlPanel from '../components/live/LiveUmpireControlPanel.vue'

const {
  loading,
  isUmpireMode,
  notice,
  fixtures,
  selectedFixtureId,
  activeMatch,
  playEvents,
  addPoint,
  undoPoint,
} = useLiveScore()
</script>

<template>
  <main class="live-score-view">
    <!-- Header Section -->
    <header class="live-header">
      <div class="header-titles">
        <div class="top-nav">
          <AuraButton to="/tournaments" variant="ghost" size="sm" icon="arrow_back">
            Quay lại Giải Đấu
          </AuraButton>
        </div>
        <h1 class="page-title">Live Score & Tỉ Số Trực Tiếp</h1>
        <p class="page-sub">Cập nhật điểm số từng pha bóng, tốc độ và phân tích thống kê giải đấu theo thời gian thực.</p>
      </div>

      <div class="header-actions">
        <AuraButton
          :variant="isUmpireMode ? 'primary' : 'outline'"
          size="md"
          icon="tune"
          @click="isUmpireMode = !isUmpireMode"
        >
          {{ isUmpireMode ? 'Đóng Chế Độ Trọng Tài' : 'Bật Chế Độ Trọng Tài (Umpire)' }}
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

    <!-- Loading Skeleton State -->
    <div v-if="loading" class="live-skeleton-stack">
      <div class="selector-skeleton-row">
        <SkeletonLoader v-for="i in 3" :key="i" type="rect" width="160px" height="42px" rounded="12px" />
      </div>
      <div class="scoreboard-skeleton-card">
        <div class="sb-sk-header">
          <SkeletonLoader type="rect" width="220px" height="18px" rounded="6px" />
          <SkeletonLoader type="rect" width="90px" height="18px" rounded="6px" />
        </div>
        <div class="sb-sk-grid">
          <div class="sb-sk-player">
            <SkeletonLoader type="rect" width="56px" height="56px" rounded="50%" />
            <div class="sb-sk-info">
              <SkeletonLoader type="rect" width="140px" height="18px" rounded="4px" />
              <SkeletonLoader type="rect" width="90px" height="12px" rounded="4px" />
            </div>
            <SkeletonLoader type="rect" width="40px" height="40px" rounded="8px" />
          </div>
          <div class="sb-sk-center">
            <SkeletonLoader type="rect" width="60px" height="20px" rounded="6px" />
            <SkeletonLoader type="rect" width="80px" height="32px" rounded="6px" />
          </div>
          <div class="sb-sk-player">
            <SkeletonLoader type="rect" width="40px" height="40px" rounded="8px" />
            <div class="sb-sk-info text-right">
              <SkeletonLoader type="rect" width="140px" height="18px" rounded="4px" />
              <SkeletonLoader type="rect" width="90px" height="12px" rounded="4px" />
            </div>
            <SkeletonLoader type="rect" width="56px" height="56px" rounded="50%" />
          </div>
        </div>
      </div>
      <div class="live-analytics-grid">
        <SkeletonLoader type="rect" width="100%" height="220px" rounded="16px" />
        <SkeletonLoader type="rect" width="100%" height="220px" rounded="16px" />
      </div>
    </div>

    <!-- Empty Live State -->
    <div v-else-if="fixtures.length === 0" class="empty-live-card">
      <div class="empty-ico-box">
        <span class="material-symbols-outlined">scoreboard</span>
      </div>
      <h4 class="empty-title">Hiện Chưa Có Trận Đấu Nào Đang Diễn Ra</h4>
      <p class="empty-sub">
        Các trận đấu trực tiếp sẽ xuất hiện tại đây ngay khi ban tổ chức và trọng tài cập nhật điểm số.
      </p>
    </div>


    <template v-else>
      <!-- Active Fixtures Selector Bar -->
      <LiveFixturesSelector
        :fixtures="fixtures"
        :selected-id="selectedFixtureId || (fixtures[0] ? fixtures[0].id : 0)"
        @select="selectedFixtureId = $event"
      />


      <!-- Main Scoreboard Display -->
      <LiveMatchScoreboard
        v-if="activeMatch"
        :match="activeMatch"
      />

      <!-- Umpire Controls (when activated) -->
      <LiveUmpireControlPanel
        v-if="isUmpireMode && activeMatch"
        :match="activeMatch"
        @add-point="addPoint"
        @undo-point="undoPoint"
      />

      <!-- Analytics & Ticker Grid -->
      <div v-if="activeMatch" class="live-analytics-grid">
        <LiveMatchStatsCard :match="activeMatch" />
        <LivePlayByPlayTicker :events="playEvents" />
      </div>
    </template>
  </main>
</template>



<style scoped>
.live-score-view {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1300px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.live-header {
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

.btn-umpire-toggle {
  padding: 10px 18px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.btn-umpire-toggle.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
}

.alert-pill {
  padding: 8px 16px;
  border-radius: 8px;
  background: #10b981;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
}

.live-analytics-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

@media (max-width: 850px) {
  .live-analytics-grid {
    grid-template-columns: 1fr;
  }
}

.empty-live-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 24px;
  text-align: center;
  background: var(--surface);
  border-radius: 20px;
  border: 1px dashed var(--outline);
}

.empty-ico-box {
  width: 56px;
  height: 56px;
  border-radius: 16px;
  background: var(--surface-container, rgba(0, 0, 0, 0.04));
  color: var(--muted);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 14px;
}

.empty-ico-box span {
  font-size: 28px;
}

.empty-title {
  margin: 0 0 6px;
  font-size: 16px;
  font-weight: 750;
  color: var(--ink);
}

.empty-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
  max-width: 420px;
}

.live-skeleton-stack {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.selector-skeleton-row {
  display: flex;
  gap: 12px;
  overflow-x: auto;
  padding-bottom: 4px;
}

.scoreboard-skeleton-card {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 24px;
  border-radius: 20px;
  background: var(--surface);
  border: 1px solid var(--outline);
}

.sb-sk-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 14px;
  border-bottom: 1px solid var(--outline);
}

.sb-sk-grid {
  display: grid;
  grid-template-columns: 1fr 140px 1fr;
  align-items: center;
  gap: 20px;
}

.sb-sk-player {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px;
  border-radius: 16px;
  background: var(--surface-container, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.sb-sk-info {
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1;
}

.sb-sk-center {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
}

@media (max-width: 850px) {
  .sb-sk-grid {
    grid-template-columns: 1fr;
  }
}
</style>



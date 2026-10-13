<script setup lang="ts">
import { useLiveScore } from '../composables/useLiveScore'
import { AuraButton, SkeletonLoader } from '@/shared/components'

import LiveFixturesSelector from '../components/live/LiveFixturesSelector.vue'
import LiveMatchScoreboard from '../components/live/LiveMatchScoreboard.vue'
import LivePlayByPlayTicker from '../components/live/LivePlayByPlayTicker.vue'
import LiveUmpireControlPanel from '../components/live/LiveUmpireControlPanel.vue'

const {
  loading,
  isUmpireMode,
  notice,
  tournamentTitle,
  fixtures,
  selectedFixtureId,
  activeMatch,
  playEvents,
  pointState,
  intervalSeconds,
  addPoint,
  undoPoint,
  toggleServer,
  switchSides,
  penaltyCard,
  triggerInterval,
} = useLiveScore()
</script>

<template>
  <main class="live-score-view">
    <!-- Header Section -->
    <header class="live-header">
      <div class="header-titles">
        <div class="top-nav">
          <AuraButton to="/tournaments" variant="ghost" size="sm" icon="arrow_back">
            Quay lại Danh Sách Giải
          </AuraButton>
          <span v-if="tournamentTitle" class="t-badge">
            <span class="material-symbols-outlined t-badge-ico">emoji_events</span>
            {{ tournamentTitle }}
          </span>
        </div>
        <h1 class="page-title">Live Score & Tỉ Số Trực Tiếp</h1>
        <p class="page-sub">Cập nhật điểm số từng pha bóng theo thời gian thực.</p>
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
      <div v-if="notice" class="alert-pill-success" role="status">
        <span class="material-symbols-outlined alert-ico">check_circle</span>
        <span>{{ notice }}</span>
      </div>
    </transition>

    <!-- Loading Skeleton State -->
    <div v-if="loading" class="live-skeleton-stack">
      <!-- Selector Row Skeleton -->
      <div class="selector-skeleton-row">
        <SkeletonLoader
          v-for="i in 4"
          :key="i"
          type="rect"
          width="180px"
          height="46px"
          rounded="12px"
        />
      </div>

      <!-- Main Scoreboard Skeleton Card -->
      <div class="scoreboard-skeleton-card">
        <div class="sb-sk-header">
          <SkeletonLoader type="rect" width="240px" height="20px" rounded="6px" />
          <SkeletonLoader type="rect" width="110px" height="22px" rounded="999px" />
        </div>
        <div class="sb-sk-grid">
          <div class="sb-sk-player">
            <SkeletonLoader type="rect" width="60px" height="60px" rounded="50%" />
            <div class="sb-sk-info">
              <SkeletonLoader type="rect" width="160px" height="20px" rounded="4px" />
              <SkeletonLoader type="rect" width="100px" height="14px" rounded="4px" />
            </div>
            <SkeletonLoader type="rect" width="48px" height="48px" rounded="10px" />
          </div>
          <div class="sb-sk-center">
            <SkeletonLoader type="rect" width="50px" height="24px" rounded="6px" />
            <SkeletonLoader type="rect" width="90px" height="36px" rounded="8px" />
          </div>
          <div class="sb-sk-player">
            <SkeletonLoader type="rect" width="48px" height="48px" rounded="10px" />
            <div class="sb-sk-info text-right">
              <SkeletonLoader type="rect" width="160px" height="20px" rounded="4px" />
              <SkeletonLoader type="rect" width="100px" height="14px" rounded="4px" />
            </div>
            <SkeletonLoader type="rect" width="60px" height="60px" rounded="50%" />
          </div>
        </div>
      </div>
    </div>

    <!-- Empty Live State -->
    <div v-else-if="fixtures.length === 0" class="empty-live-card">
      <div class="empty-ico-box">
        <span class="material-symbols-outlined">scoreboard</span>
      </div>
      <h4 class="empty-title">Hiện Chưa Có Trận Đấu Nào Đang Diễn Ra</h4>
      <p class="empty-sub">
        Các trận đấu trực tiếp sẽ xuất hiện tại đây ngay khi ban tổ chức và trọng tài cập nhật điểm
        số.
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
      <LiveMatchScoreboard v-if="activeMatch" :match="activeMatch" :point-state="pointState" />

      <!-- Umpire Controls (when activated) -->
      <LiveUmpireControlPanel
        v-if="isUmpireMode && activeMatch"
        :match="activeMatch"
        :point-state="pointState"
        :interval-seconds="intervalSeconds"
        @add-point="addPoint"
        @undo-point="undoPoint"
        @toggle-server="toggleServer"
        @switch-sides="switchSides"
        @penalty-card="penaltyCard"
        @trigger-interval="triggerInterval"
      />

      <!-- Rally ticker: only what the umpire has recorded in this session -->
      <LivePlayByPlayTicker v-if="playEvents.length" :events="playEvents" />
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
  padding: 28px 24px 60px;
  color: var(--ink);
}

.live-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 16px;
}

.header-titles {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.top-nav {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.t-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 700;
  color: var(--pink-dark, #db2777);
  background: rgba(219, 39, 119, 0.08);
  border: 1px solid rgba(219, 39, 119, 0.2);
  padding: 4px 12px;
  border-radius: 999px;
}

.t-badge-ico {
  font-size: 14px;
}

.page-title {
  margin: 0;
  font-size: 24px;
  font-weight: 900;
  color: var(--ink);
}

.page-sub {
  margin: 0;
  font-size: 13.5px;
  color: var(--muted);
}

.alert-pill-success {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 12px;
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.3);
  font-size: 13px;
  font-weight: 650;
  width: fit-content;
}

.alert-ico {
  font-size: 18px;
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
